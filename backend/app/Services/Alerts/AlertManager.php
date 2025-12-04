<?php

namespace App\Services\Alerts;

use App\Jobs\SendOrderEmail;
use App\Models\Alert;
use App\Models\Consumable;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrinterStatusSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlertManager
{
    public function evaluate(Printer $printer, PrinterStatusSnapshot $snapshot): void
    {
        $this->handleOfflineStatus($printer, $snapshot);
        $this->handleConsumables($printer, $snapshot);
    }

    protected function handleOfflineStatus(Printer $printer, PrinterStatusSnapshot $snapshot): void
    {
        $offlineStatuses = collect(config('alerts.offline_statuses', []));

        if ($offlineStatuses->contains($snapshot->status)) {
            // Obtener los últimos 3 snapshots ordenados por fecha descendente
            $recentSnapshots = $printer->snapshots()
                ->latest('captured_at')
                ->limit(3)
                ->get();
            
            // Verificar que los 3 últimos snapshots sean todos offline consecutivos
            $consecutiveOfflineCount = 0;
            foreach ($recentSnapshots as $recentSnapshot) {
                if ($offlineStatuses->contains($recentSnapshot->status)) {
                    $consecutiveOfflineCount++;
                } else {
                    break; // Si encontramos uno online, rompemos la secuencia
                }
            }

            // Solo crear alerta después de 3 sincronizaciones consecutivas offline
            if ($consecutiveOfflineCount >= 3) {
                // Buscar si ya existe una alerta para este problema (en cualquier estado excepto resolved)
                $existingAlert = Alert::where('printer_id', $printer->id)
                    ->where('type', 'PRINTER_OFFLINE')
                    ->where('status', '!=', 'resolved')
                    ->first();

                // Si no existe o está resuelta, crear/actualizar solo si no hay ninguna pendiente de revisar
                if (!$existingAlert) {
                    Alert::create([
                        'severity' => 'critical',
                        'source' => 'snmp',
                        'title' => "Impresora {$printer->name} sin conexión",
                        'message' => 'La impresora ha estado offline durante 3 sincronizaciones consecutivas.',
                        'printer_id' => $printer->id,
                        'type' => 'PRINTER_OFFLINE',
                        'status' => 'open',
                        'payload' => [
                            'status' => $snapshot->status,
                            'last_seen_at' => $printer->last_seen_at,
                            'consecutive_offline_count' => $consecutiveOfflineCount,
                        ],
                    ]);
                }
                // Si ya existe una alerta (open o acknowledged), no hacer nada - esperar a que se revise manualmente
            }

            return;
        }

        // Si está online, solo resolver alertas offline que estén en estado 'open' (no las reconocidas manualmente)
        Alert::where('printer_id', $printer->id)
            ->where('type', 'PRINTER_OFFLINE')
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    protected function handleConsumables(Printer $printer, PrinterStatusSnapshot $snapshot): void
    {
        $consumables = collect($snapshot->consumables ?? []);
        if ($consumables->isEmpty()) {
            return;
        }

        $criticalThreshold = 5; // Critical si está por debajo del 5%
        $mediumThreshold = 15; // Medium si está por debajo del 15%
        $release = 30; // Resolver si sube por encima del 30%

        $consumables->each(function (array $item) use ($printer, $criticalThreshold, $mediumThreshold, $release) {
            $level = $item['level'] ?? $item['nivel_porcentaje'] ?? null;
            $slot = $item['slot'] ?? $item['label'] ?? $item['name'] ?? 'unknown';

            if ($level === null) {
                return;
            }

            // Buscar si ya existe una alerta para este consumible (en cualquier estado excepto resolved)
            $existingAlert = Alert::where('printer_id', $printer->id)
                ->where('type', 'LOW_CONSUMABLE')
                ->where('status', '!=', 'resolved')
                ->where('payload->slot', $slot)
                ->first();

            // Determinar severidad basada en el nivel (solo si está POR DEBAJO del umbral)
            // level < 5: critical, level < 15: medium, level >= 15: no alerta
            $severity = null;
            if ($level < $criticalThreshold) {
                $severity = 'critical';
            } elseif ($level < $mediumThreshold) {
                $severity = 'medium';
            }

            // Si el nivel está por encima del umbral de resolución (30%), resolver solo alertas 'open'
            if ($level >= $release) {
                if ($existingAlert && $existingAlert->status === 'open') {
                    // Solo resolver alertas que estén en estado 'open' (nunca las reconocidas manualmente)
                    $existingAlert->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'payload->level' => $level,
                    ]);
                }
                // Si está en 'acknowledged' o no existe, no hacer nada
                return;
            }

            // Si el nivel está por debajo del umbral, crear o actualizar alerta
            if ($severity) {
                // Si ya existe una alerta para este problema
                if ($existingAlert) {
                    // NUNCA modificar alertas reconocidas manualmente - esperar a que se revisen manualmente
                    if ($existingAlert->status === 'acknowledged') {
                        return; // No hacer nada si está reconocida
                    }

                    // Solo actualizar si está en estado 'open'
                    if ($existingAlert->status === 'open') {
                        // Actualizar severidad y nivel si cambió
                        $payload = $existingAlert->payload ?? [];
                        $payload['level'] = $level;
                        
                        if ($existingAlert->severity !== $severity) {
                            $existingAlert->update([
                                'severity' => $severity,
                                'message' => "Nivel actual: {$level}%",
                                'payload' => $payload,
                            ]);
                        } else {
                            // Solo actualizar el mensaje y nivel sin cambiar severidad
                            $existingAlert->update([
                                'message' => "Nivel actual: {$level}%",
                                'payload' => $payload,
                            ]);
                        }
                    }
                } else {
                    // No existe alerta, crear una nueva solo si el nivel está por debajo del umbral
                    Alert::create([
                        'type' => 'LOW_CONSUMABLE',
                        'severity' => $severity,
                        'status' => 'open',
                        'source' => 'snmp',
                        'title' => "Consumible {$slot} bajo en {$printer->name}",
                        'message' => "Nivel actual: {$level}%",
                        'printer_id' => $printer->id,
                        'payload' => [
                            'slot' => $slot,
                            'label' => $item['label'] ?? $item['name'] ?? $slot,
                            'level' => $level,
                        ],
                    ]);

                    // Crear pedido automático si no existe uno pendiente para este consumible
                    $this->createOrderIfNeeded($printer, $item);
                }
            } else {
                // El nivel está entre 15% y 30% - actualizar payload si existe alerta 'open', pero mantenerla activa
                if ($existingAlert && $existingAlert->status === 'open') {
                    $payload = $existingAlert->payload ?? [];
                    $payload['level'] = $level;
                    $existingAlert->update([
                        'message' => "Nivel actual: {$level}%",
                        'payload' => $payload,
                    ]);
                }
            }
        });
    }

    /**
     * Crea un pedido automático si no existe uno pendiente
     */
    protected function createOrderIfNeeded(Printer $printer, array $consumableData): void
    {
        // Buscar consumible por nombre/color
        $consumableName = $consumableData['name'] ?? null;
        $consumableColor = $consumableData['color'] ?? null;

        if (! $consumableName) {
            return;
        }

        // Intentar encontrar el consumible en la BD
        $consumable = Consumable::where('brand', $printer->brand)
            ->where(function ($query) use ($consumableName, $consumableColor) {
                $query->where('name', 'like', "%{$consumableName}%");
                if ($consumableColor) {
                    $query->orWhere('color', $consumableColor);
                }
            })
            ->first();

        // Verificar si ya existe un pedido pendiente para esta impresora y consumible
        $existingOrder = Order::where('printer_id', $printer->id)
            ->where('status', 'pending')
            ->when($consumable, fn ($q) => $q->where('consumable_id', $consumable->id))
            ->first();

        if ($existingOrder) {
            return;
        }

        // Obtener email del proveedor desde configuración de notificaciones
        $notificationConfig = \App\Models\NotificationConfig::where('type', 'email')
            ->where('is_active', true)
            ->first();

        if (! $notificationConfig || empty($notificationConfig->recipients)) {
            Log::warning("No notification config or recipients found, cannot create automatic order");
            return;
        }

        $emailTo = is_array($notificationConfig->recipients)
            ? ($notificationConfig->recipients[0] ?? null)
            : $notificationConfig->recipients;

        if (! $emailTo) {
            return;
        }

        // Crear el pedido
        $order = Order::create([
            'printer_id' => $printer->id,
            'consumable_id' => $consumable?->id,
            'status' => 'pending',
            'requested_at' => now(),
            'email_to' => $emailTo,
            'notes' => "Pedido automático generado por alerta de consumible bajo: {$consumableName}",
        ]);

        // Encolar envío de email
        SendOrderEmail::dispatch($order);

        Log::info("Automatic order created for printer {$printer->id}, consumable {$consumableName}");
    }
}


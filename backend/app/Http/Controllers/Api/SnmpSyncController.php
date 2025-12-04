<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PollPrinterSnmp;
use App\Models\Printer;
use App\Models\SnmpSyncConfig;
use App\Models\SnmpSyncHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SnmpSyncController extends Controller
{
    public function syncAll(): JsonResponse
    {
        $printers = Printer::where('supports_snmp', true)->get();
        $totalPrinters = $printers->count();
        
        // Crear registro de historial
        $history = SnmpSyncHistory::create([
            'type' => 'manual',
            'total_printers' => $totalPrinters,
            'dispatched' => 0,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $dispatched = 0;

        try {
            $history->markAsRunning();

            foreach ($printers as $printer) {
                // Pasar el historyId al job para que actualice el contador
                PollPrinterSnmp::dispatch($printer, $history->id);
                $dispatched++;
            }

            $history->update(['dispatched' => $dispatched]);
            // NO marcar como completado aquí - los jobs lo harán cuando terminen

            return response()->json([
                'message' => "Se encolaron {$dispatched} impresoras para sincronización",
                'dispatched' => $dispatched,
                'history_id' => $history->id,
            ]);
        } catch (\Exception $e) {
            $history->markAsFailed($e->getMessage());
            
            return response()->json([
                'message' => 'Error al sincronizar impresoras',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHistory(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 20);
        
        $history = SnmpSyncHistory::orderByDesc('started_at')
            ->limit($limit)
            ->get();

        return response()->json($history);
    }

    public function getConfig(): JsonResponse
    {
        $enabled = SnmpSyncConfig::isEnabled('auto_sync_enabled');
        $frequency = (int) SnmpSyncConfig::get('auto_sync_frequency', 15);

        return response()->json([
            'auto_sync_enabled' => $enabled,
            'auto_sync_frequency' => $frequency,
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_sync_enabled' => ['required', 'boolean'],
            'auto_sync_frequency' => ['required', 'integer', 'min:1', 'max:1440'], // Máximo 24 horas (1440 minutos)
        ]);

        SnmpSyncConfig::set('auto_sync_enabled', $validated['auto_sync_enabled'] ? 'true' : 'false', 'Habilitar sincronización automática');
        SnmpSyncConfig::set('auto_sync_frequency', (string) $validated['auto_sync_frequency'], 'Frecuencia de sincronización automática en minutos');

        // Actualizar cron job si está habilitado
        if ($validated['auto_sync_enabled']) {
            $this->updateCronJob($validated['auto_sync_frequency']);
        } else {
            $this->removeCronJob();
        }

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'config' => [
                'auto_sync_enabled' => $validated['auto_sync_enabled'],
                'auto_sync_frequency' => $validated['auto_sync_frequency'],
            ],
        ]);
    }

    private function updateCronJob(int $frequency): void
    {
        // No es necesario hacer nada, el script Python lee la configuración de la BD cada vez
        \Log::info('Configuración de sincronización actualizada', ['frequency' => $frequency]);
    }

    private function removeCronJob(): void
    {
        // No es necesario hacer nada, el script Python verifica si está habilitado
        \Log::info('Sincronización automática deshabilitada');
    }
}

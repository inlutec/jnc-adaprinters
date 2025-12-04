<?php

namespace App\Console\Commands;

use App\Jobs\PollPrinterSnmp;
use App\Models\Printer;
use App\Models\SnmpSyncHistory;
use Illuminate\Console\Command;

class PollPrinters extends Command
{
    protected $signature = 'printers:poll
        {printer? : ID numérico o UUID de la impresora}
        {--site= : ID de la sede}
        {--status= : Estado deseado (online/offline/...)}
        {--limit= : Número máximo de impresoras a encolar}
        {--auto-check : Verificar configuración automática antes de ejecutar}';

    protected $description = 'Encola jobs de sondeo SNMP para las impresoras seleccionadas';

    public function handle(): void
    {
        // Si se llama con --auto-check, verificar si debe ejecutarse
        if ($this->option('auto-check')) {
            if (!\App\Models\SnmpSyncConfig::isEnabled('auto_sync_enabled')) {
                $this->line('Sincronización automática deshabilitada. Saltando ejecución.');
                return;
            }
            
            $frequency = (int) \App\Models\SnmpSyncConfig::get('auto_sync_frequency', 15);
            
            // Obtener la última sincronización automática exitosa
            $lastSync = SnmpSyncHistory::where('type', 'automatic')
                ->where('status', 'completed')
                ->latest('completed_at')
                ->first();
            
            // Si hay una sincronización previa, verificar si ha pasado el tiempo configurado
            if ($lastSync && $lastSync->completed_at) {
                $nextRun = $lastSync->completed_at->copy()->addMinutes($frequency);
                if (now()->lessThan($nextRun)) {
                    $this->line("Sincronización automática programada para: {$nextRun}. Saltando ejecución.");
                    return;
                }
            }
            
            $this->line("Sincronización automática: Ejecutando ahora (frecuencia: {$frequency} minutos).");
        }

        $query = Printer::where('supports_snmp', true);

        if ($printerId = $this->argument('printer')) {
            $query->where(function ($builder) use ($printerId) {
                $builder->where('id', $printerId)->orWhere('uuid', $printerId);
            });
        }

        if ($siteId = $this->option('site')) {
            $query->where('site_id', $siteId);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $totalPrinters = $query->count();
        $limit = (int) $this->option('limit');
        
        // Crear registro de historial para sincronización automática
        $history = null;
        $isAutomatic = $this->option('auto-check') && !$this->argument('printer') && !$this->option('site') && !$this->option('status');
        
        if ($isAutomatic) {
            $history = SnmpSyncHistory::create([
                'type' => 'automatic',
                'total_printers' => $totalPrinters,
                'dispatched' => 0,
                'status' => 'pending',
                'started_at' => now(),
            ]);
            $history->markAsRunning();
        }

        $dispatched = 0;

        try {
            $query->orderBy('id')->chunkById(50, function ($printers) use (&$dispatched, $limit, $history) {
                foreach ($printers as $printer) {
                    PollPrinterSnmp::dispatch($printer, $history?->id);
                    $dispatched++;

                    if ($limit > 0 && $dispatched >= $limit) {
                        return false;
                    }
                }

                return true;
            });

            if ($history) {
                $history->update(['dispatched' => $dispatched]);
                // No marcar como completado aquí, los jobs lo harán
            }

            if ($isAutomatic) {
                $this->info("Sincronización automática: Se encolaron {$dispatched} impresoras para sondeo SNMP.");
            } else {
                $this->info("Se encolaron {$dispatched} impresoras para sondeo SNMP.");
            }
        } catch (\Exception $e) {
            if ($history) {
                $history->markAsFailed($e->getMessage());
            }
            $this->error("Error al encolar impresoras: {$e->getMessage()}");
        }
    }
}

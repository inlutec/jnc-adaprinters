<?php

namespace App\Jobs;

use App\Models\Printer;
use App\Models\PrinterPrintLog;
use App\Models\PrinterStatusSnapshot;
use App\Models\SnmpSyncHistory;
use App\Services\Alerts\AlertManager;
use App\Services\Snmp\SnmpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollPrinterSnmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Printer $printer, public ?int $historyId = null)
    {
        $this->onQueue(config('snmp.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(SnmpClient $snmpClient, AlertManager $alertManager): void
    {
        $printer = $this->printer->fresh();

        if (! $printer) {
            return;
        }

        try {
            $result = $snmpClient->poll($printer);
        } catch (Throwable $exception) {
            Log::warning('SNMP polling failed', [
                'printer_id' => $printer->id,
                'message' => $exception->getMessage(),
            ]);

            $printer->update([
                'status' => 'error',
                'last_sync_at' => now(),
            ]);

            // Actualizar historial si existe
            if ($this->historyId) {
                $this->updateHistory(false);
            }

            return;
        }

        // Obtener el snapshot anterior ANTES de crear el nuevo (importante para evitar race conditions)
        // Usar orderBy en lugar de latest para mayor control y asegurar que obtenemos el snapshot más reciente
        $previousSnapshot = $printer->snapshots()
            ->orderBy('captured_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        // Log para debugging
        if ($previousSnapshot) {
            Log::debug('Previous snapshot found for print log', [
                'printer_id' => $printer->id,
                'previous_snapshot_id' => $previousSnapshot->id,
                'previous_total_pages' => $previousSnapshot->total_pages,
                'previous_captured_at' => $previousSnapshot->captured_at->toDateTimeString(),
            ]);
        } else {
            Log::info('No previous snapshot found for print log', [
                'printer_id' => $printer->id,
            ]);
        }

        $snapshot = $printer->snapshots()->create([
            'status' => $result['status'] ?? 'unknown',
            'error_code' => $result['error_code'] ?? null,
            'total_pages' => Arr::get($result, 'counters.total_pages'),
            'color_pages' => Arr::get($result, 'counters.color_pages'),
            'bw_pages' => Arr::get($result, 'counters.bw_pages'),
            'uptime_seconds' => $result['uptime_seconds'] ?? null,
            'consumables' => $result['consumables'] ?? [],
            'counters' => $result['counters'] ?? [],
            'environment' => $result['environment'] ?? [],
            'raw_payload' => $result['raw'] ?? $result,
            'captured_at' => now(),
        ]);

        // Determinar si la impresora está online basándose en el resultado
        $isOnline = ($result['status'] ?? 'unknown') === 'online';
        
        $printer->fill([
            'status' => $snapshot->status,
            'snmp_data' => $result,
            'metrics' => [
                'total_pages' => $snapshot->total_pages,
                'color_pages' => $snapshot->color_pages,
                'bw_pages' => $snapshot->bw_pages,
            ],
            'last_sync_at' => now(),
            // Actualizar last_seen_at si está online, mantener el anterior si está offline
            'last_seen_at' => $isOnline ? now() : ($printer->last_seen_at ?? now()->subMinutes(20)),
        ])->save();

        $this->createPrintLog($printer, $snapshot, $previousSnapshot);

        $alertManager->evaluate($printer, $snapshot);

        // Actualizar historial si existe
        if ($this->historyId) {
            $this->updateHistory(true);
        }
    }

    protected function updateHistory(bool $success): void
    {
        if (!$this->historyId) {
            return;
        }

        $history = SnmpSyncHistory::find($this->historyId);
        if (!$history) {
            return;
        }

        // Usar incremento atómico para evitar condiciones de carrera
        if ($success) {
            $history->increment('completed');
        } else {
            $history->increment('failed');
        }

        // Verificar si todos los jobs han terminado
        // Usar una consulta fresca para evitar problemas de caché
        $history = SnmpSyncHistory::find($this->historyId);
        $totalProcessed = $history->completed + $history->failed;
        
        if ($totalProcessed >= $history->dispatched && $history->status === 'running') {
            // Todos los jobs han terminado, marcar como completado
            $history->markAsCompleted($history->completed, $history->failed);
        }
    }

    protected function createPrintLog(
        Printer $printer,
        PrinterStatusSnapshot $current,
        ?PrinterStatusSnapshot $previous
    ): void {
        // Si no hay snapshot anterior, no podemos calcular diferencias
        if (!$previous) {
            Log::info('Print log skipped: no previous snapshot', [
                'printer_id' => $printer->id,
                'current_snapshot_id' => $current->id,
            ]);
            return;
        }
        
        // Asegurarse de que no estamos comparando el mismo snapshot
        if ($current->id === $previous->id) {
            Log::warning('Print log skipped: current and previous snapshots are the same', [
                'printer_id' => $printer->id,
                'snapshot_id' => $current->id,
            ]);
            return;
        }
        
        // Obtener valores del snapshot anterior
        $previousColor = $previous->color_pages ?? 0;
        $previousBw = $previous->bw_pages ?? 0;
        $previousTotal = $previous->total_pages ?? 0;
        
        // Obtener valores del snapshot actual
        $currentColor = $current->color_pages ?? 0;
        $currentBw = $current->bw_pages ?? 0;
        $currentTotal = $current->total_pages ?? 0;
        
        // Log para debugging
        Log::debug('Creating print log', [
            'printer_id' => $printer->id,
            'previous_snapshot_id' => $previous->id,
            'current_snapshot_id' => $current->id,
            'previous_total' => $previousTotal,
            'current_total' => $currentTotal,
            'previous_color' => $previousColor,
            'current_color' => $currentColor,
            'previous_bw' => $previousBw,
            'current_bw' => $currentBw,
        ]);
        
        // Calcular el delta del contador total (fuente de verdad principal)
        // Si el total baja, asumimos reset completo y no crear registro
        if ($currentTotal < $previousTotal) {
            Log::warning('Print log skipped: total counter reset detected', [
                'printer_id' => $printer->id,
                'previous_total' => $previousTotal,
                'current_total' => $currentTotal,
            ]);
            return;
        }
        
        $totalDelta = $currentTotal - $previousTotal;
        
        // Calcular diferencias de color y BW
        $colorDelta = ($currentColor >= $previousColor) 
            ? ($currentColor - $previousColor) 
            : 0; // Reset parcial en color: no contamos
        
        $bwDelta = ($currentBw >= $previousBw) 
            ? ($currentBw - $previousBw) 
            : 0; // Reset parcial en BW: no contamos
        
        // Si hay un reset parcial, ajustar para que color + BW = total
        // Si la suma no coincide con el total, distribuir proporcionalmente
        $calculatedTotal = $colorDelta + $bwDelta;
        
        if ($calculatedTotal != $totalDelta && $totalDelta > 0) {
            // Hay inconsistencia: usar el total como fuente de verdad y distribuir
            if ($calculatedTotal > 0) {
                // Distribuir proporcionalmente según los deltas calculados
                $colorRatio = $calculatedTotal > 0 ? ($colorDelta / $calculatedTotal) : 0.5;
                $colorDelta = (int) round($totalDelta * $colorRatio);
                $bwDelta = $totalDelta - $colorDelta;
            } else {
                // Ambos contadores resetean, distribuir 50/50 o según ratio histórico
                $previousColorTotal = $previousColor + $previousBw;
                $colorRatio = $previousColorTotal > 0 ? ($previousColor / $previousColorTotal) : 0.5;
                $colorDelta = (int) round($totalDelta * $colorRatio);
                $bwDelta = $totalDelta - $colorDelta;
            }
        }

        // Validar que el total tenga sentido: debe ser > 0 y razonable (< 10000 por sincronización)
        if ($totalDelta <= 0 || $totalDelta > 10000) {
            Log::warning('Print log skipped: invalid total delta', [
                'printer_id' => $printer->id,
                'total_delta' => $totalDelta,
                'current_total' => $currentTotal,
                'previous_total' => $previousTotal,
            ]);
            return;
        }

        PrinterPrintLog::create([
            'printer_id' => $printer->id,
            'snapshot_id' => $current->id,
            'start_counter' => $previousTotal,
            'end_counter' => $currentTotal,
            'color_counter_total' => $currentColor,
            'bw_counter_total' => $currentBw,
            'total_prints' => $totalDelta,
            'color_prints' => max(0, $colorDelta),
            'bw_prints' => max(0, $bwDelta),
            'started_at' => $previous->captured_at,
            'ended_at' => $current->captured_at,
            'source' => 'snmp',
            'metadata' => [
                'simulated' => config('snmp.driver') === 'fake',
            ],
        ]);
    }
}

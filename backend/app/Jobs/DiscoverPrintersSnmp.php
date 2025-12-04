<?php

namespace App\Jobs;

use App\Models\Printer;
use App\Services\Snmp\SnmpDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverPrintersSnmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(
        public string $ipRange,
        public ?int $provinceId = null,
        public ?int $siteId = null,
        public ?int $departmentId = null,
    ) {
        $this->onQueue(config('snmp.queue', 'default'));
    }

    public function handle(SnmpDiscoveryService $discoveryService): void
    {
        Log::info("Starting SNMP discovery for range: {$this->ipRange}");

        $results = $discoveryService->discover($this->ipRange);

        $discovered = 0;
        $created = 0;

        foreach ($results as $result) {
            if (! $result['data']) {
                continue;
            }

            $discovered++;

            // Verificar si ya existe una impresora con esta IP
            $existing = Printer::where('ip_address', $result['ip'])->first();

            if ($existing) {
                Log::info("Printer with IP {$result['ip']} already exists, skipping");
                continue;
            }

            try {
                $discoveryService->createPrinterFromDiscovery(
                    $result['data'],
                    $this->provinceId,
                    $this->siteId,
                    $this->departmentId
                );
                $created++;
                Log::info("Created printer from discovery: {$result['ip']}");
            } catch (\Exception $e) {
                Log::error("Failed to create printer from discovery {$result['ip']}: {$e->getMessage()}");
            }
        }

        Log::info("Discovery completed: {$discovered} printers discovered, {$created} created");
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverPrintersSnmp;
use Illuminate\Console\Command;

class DiscoverPrinters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printers:discover 
                            {ip_range : IP address or range (e.g., 10.64.130.12 or 10.64.130.0/24)}
                            {--province= : Province ID}
                            {--site= : Site ID}
                            {--department= : Department ID}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover printers via SNMP scan on IP range.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ipRange = $this->argument('ip_range');
        $provinceId = $this->option('province') ? (int) $this->option('province') : null;
        $siteId = $this->option('site') ? (int) $this->option('site') : null;
        $departmentId = $this->option('department') ? (int) $this->option('department') : null;

        if ($this->option('sync')) {
            $job = new DiscoverPrintersSnmp($ipRange, $provinceId, $siteId, $departmentId);
            $job->handle(app(\App\Services\Snmp\SnmpDiscoveryService::class));
            $this->info('Discovery completed synchronously.');
        } else {
            DiscoverPrintersSnmp::dispatch($ipRange, $provinceId, $siteId, $departmentId);
            $this->info("Discovery job queued for range: {$ipRange}");
        }
    }
}

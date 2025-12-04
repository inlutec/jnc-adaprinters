<?php

namespace App\Services\Snmp;

use App\Models\Printer;
use App\Services\Snmp\Drivers\FakeSnmpDriver;
use App\Services\Snmp\Drivers\RealSnmpDriver;
use App\Services\Snmp\SnmpDiscoveryService;

class SnmpClient
{
    public function __construct(
        protected RealSnmpDriver $realDriver,
        protected FakeSnmpDriver $fakeDriver,
        protected SnmpDiscoveryService $discoveryService,
    ) {
    }

    public function poll(Printer $printer): ?array
    {
        return match (config('snmp.driver', 'fake')) {
            'real' => $this->realDriver->poll($printer),
            'fake' => $this->fakeDriver->poll($printer),
            default => $this->fakeDriver->poll($printer),
        };
    }

    public function discover(string $ipAddress, ?string $community = null): ?array
    {
        return $this->discoveryService->discoverSingle($ipAddress);
    }
}


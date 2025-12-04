<?php

return [
    'driver' => env('SNMP_DRIVER', 'fake'),
    'queue' => env('SNMP_QUEUE', 'default'),
    'timeout_ms' => (int) env('SNMP_TIMEOUT_MS', 1500),
    'retries' => (int) env('SNMP_RETRIES', 2),
    'community' => env('SNMP_COMMUNITY', 'public'),
];


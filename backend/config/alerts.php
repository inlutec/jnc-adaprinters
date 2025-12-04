<?php

return [
    'consumable_threshold' => (int) env('ALERT_CONSUMABLE_THRESHOLD', 15),
    'consumable_release_threshold' => (int) env('ALERT_CONSUMABLE_RELEASE', 30),
    'offline_statuses' => ['error', 'offline', 'unknown'],
    'alert_ttl_minutes' => (int) env('ALERT_DEFAULT_TTL', 60 * 24),
];


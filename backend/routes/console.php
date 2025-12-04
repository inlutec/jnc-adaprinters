<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincronización automática SNMP - ejecutar cada minuto, el comando verifica la frecuencia internamente
Schedule::command('printers:poll --auto-check')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/snmp_sync.log'))
    ->name('snmp-auto-sync');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/scheduler.log'))
    ->name('horizon-snapshot');

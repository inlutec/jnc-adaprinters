<?php

namespace App\Console;

use App\Models\SnmpSyncConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sincronización automática SNMP - ejecutar cada minuto, el comando verifica la frecuencia internamente
        $schedule->command('printers:poll --auto-check')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/snmp_sync.log'))
            ->name('snmp-auto-sync');

        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes()
            ->appendOutputTo(storage_path('logs/scheduler.log'))
            ->name('horizon-snapshot');
    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}


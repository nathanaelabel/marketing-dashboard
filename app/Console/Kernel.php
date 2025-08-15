<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Adempiere Data Sync Scheduler
        // --------------------------------------------------------------------
        // Runs the fast, incremental sync every 30 minutes to catch recent changes.
        $schedule->command('app:sync-all-adempiere-data --type=incremental')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/sync-incremental.log'));

        // Runs the full, robust sync and prune once daily to ensure 1:1 data parity.
        $schedule->command('app:sync-all-adempiere-data --type=full')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/sync-full.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}

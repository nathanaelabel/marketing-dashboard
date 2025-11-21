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
        // Data Sync Scheduler
        // --------------------------------------------------------------------
        // Runs full sync daily at 08:30 WIB (Asia/Jakarta timezone)
        // This ensures 1:1 data parity with all 17 branch databases
        // Uses optimized 3-month date filter for daily updates (data before this is already complete)
        $schedule->command('app:sync-all')
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/sync-full.log'))
            ->appendOutputTo(storage_path('logs/sync-full.log'));
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

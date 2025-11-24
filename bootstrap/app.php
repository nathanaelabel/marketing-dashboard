<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Data Sync Scheduler
        // --------------------------------------------------------------------
        // Runs full sync daily at 08:30 WIB
        // This ensures 1:1 data parity with all 17 branch databases
        // Uses optimized 3-month date filter for daily updates (data before this is already complete)
        $schedule->command('app:sync-all')
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/sync-full.log'))
            ->appendOutputTo(storage_path('logs/sync-full.log'));

        // Return Comparison Cache Refresh
        // --------------------------------------------------------------------
        // Refresh cache for current month daily at 06:00 WIB
        // This ensures data for ongoing month is always up-to-date
        // Past months remain cached for 24 hours (data is final)
        $schedule->command('cache:warm-return-comparison --current')
            ->dailyAt('06:00')
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/cache-return-comparison.log'))
            ->appendOutputTo(storage_path('logs/cache-return-comparison.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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
        // Runs full sync daily at 08:30 WIB (Asia/Jakarta timezone)
        // This ensures 1:1 data parity with all 17 branch databases
        // Uses optimized 3-month date filter for daily updates (data before this is already complete)
        $schedule->command('app:sync-all')
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/sync-full.log'))
            ->appendOutputTo(storage_path('logs/sync-full.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

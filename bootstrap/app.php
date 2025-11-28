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
        // Data Sync Scheduler - Cabang Utama (12 cabang)
        // --------------------------------------------------------------------
        // Runs daily at 18:00 WIB for branches with 24-hour active servers
        // TRG, BKS, JKT, LMP, BDG, MKS, SBY, SMG, PWT, DPS, PDG, MDN
        // Failed connections will be automatically queued for morning retry
        $schedule->command('app:sync-all')
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/sync-full.log'))
            ->appendOutputTo(storage_path('logs/sync-full.log'));

        // Data Sync Scheduler - Cabang Pagi (5 cabang + retry failed dari sore)
        // --------------------------------------------------------------------
        // Runs daily at 09:00 WIB for branches with servers inactive at night
        // BJM, PKU, PLB, PTK, CRB + auto-retry failed connections from evening sync
        $schedule->command('app:sync-all --sync-group=adempiere_morning --retry-failed')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/sync-morning.log'))
            ->appendOutputTo(storage_path('logs/sync-morning.log'));

        // Return Comparison Cache Refresh
        // --------------------------------------------------------------------
        // Refresh cache for last 3 months daily at 06:00 WIB
        // This ensures recent data is always up-to-date
        $schedule->command('cache:warm-return-comparison --last-3-months')
            ->dailyAt('06:00')
            ->timezone('Asia/Jakarta')
            ->sendOutputTo(storage_path('logs/cache-return-comparison.log'))
            ->appendOutputTo(storage_path('logs/cache-return-comparison.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

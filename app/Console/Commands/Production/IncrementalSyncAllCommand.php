<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IncrementalSyncAllCommand extends Command
{
    protected $signature = 'app:incremental-sync-all';
    protected $description = 'Run incremental sync for all tables every 30 minutes';

    public function handle()
    {
        $this->info('====================================================================');
        $this->info('Starting Incremental Sync for All Tables');
        $this->info('====================================================================');
        $this->line('');

        Log::info("IncrementalSyncAll: Starting process");

        // Tables that support incremental sync (have created_at and updated_at columns)
        $incrementalSyncTables = [
            'MProductprice',
            'CInvoice',
            'COrder',
            'COrderline',
            'CAllocationhdr',
            'CAllocationline',
            'MInout',
            'MInoutline',
            'CInvoiceline',
            'MMatchinv',
            'MStorage',
            'CBpartner',
            'CBpartnerLocation'
        ];

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($incrementalSyncTables as $modelName) {
            $this->line('');
            $this->info("--- Incremental sync for {$modelName} ---");
            
            try {
                $exitCode = $this->call('app:incremental-sync-table', [
                    'model' => $modelName
                ]);

                if ($exitCode === Command::SUCCESS || $exitCode === 0) {
                    $totalSuccess++;
                    $this->info("✓ Successfully synced {$modelName}");
                } else {
                    $totalFailed++;
                    $this->error("✗ Failed to sync {$modelName}");
                    Log::error("IncrementalSyncAll: Failed to sync", [
                        'model' => $modelName,
                        'exit_code' => $exitCode
                    ]);
                }
            } catch (\Exception $e) {
                $totalFailed++;
                $this->error("✗ Exception while syncing {$modelName}: " . $e->getMessage());
                Log::error("IncrementalSyncAll: Exception", [
                    'model' => $modelName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->line('');
        $this->info('====================================================================');
        $this->info("Incremental Sync Summary: {$totalSuccess} succeeded, {$totalFailed} failed");
        $this->info('====================================================================');

        Log::info("IncrementalSyncAll: Completed", [
            'success' => $totalSuccess,
            'failed' => $totalFailed
        ]);

        return Command::SUCCESS;
    }
}

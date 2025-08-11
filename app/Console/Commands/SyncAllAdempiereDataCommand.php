<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncAllAdempiereDataCommand extends Command
{
    protected $signature = 'app:sync-all-adempiere-data {--type=full : The type of sync to perform (full|incremental)}';
    protected $description = 'Orchestrates the synchronization of all Adempiere tables.';

    public function handle()
    {
        $this->info('Starting full Adempiere data synchronization process...');

        // Tables to be synced from a single source (pwmsby)
        $singleSourceTables = [
            'AdOrg',
            'MProduct',
            'MProductCategory',
            'MProductsubcat',
        ];

        // Tables that require merging from multiple sources without pruning
        $mergeOnlyTables = [
            ['model' => 'MLocator', 'incremental' => false],
            ['model' => 'MStorage', 'incremental' => false],
        ];

        // Transactional tables that require a full 1:1 sync (sync and prune)
        $syncAndPruneTables = [
            'CInvoice',
            'CInvoiceline',
            'COrder',
            'COrderline',
            'CAllocationhdr',
            'CAllocationline',
        ];

        // --- Execute Sync for single-source tables from pwmsby ---
        $this->line('--- Syncing single-source tables from pwmsby ---');
        foreach ($singleSourceTables as $model) {
            $this->callSyncCommand($model, 'pgsql_pwmsby', false); // false for full sync (merge mode)
        }

        // --- Execute Sync for multi-source merge-only tables ---
        $this->line('--- Syncing multi-source merge-only tables ---');
        $connections = config('database.sync_connections.adempiere', ['pgsql_surabaya', 'pgsql_bandung', 'pgsql_jakarta']);
        foreach ($mergeOnlyTables as $tableInfo) {
            foreach ($connections as $connection) {
                $this->line("--- Processing {$tableInfo['model']} from connection: {$connection} ---");
                $this->callSyncCommand($tableInfo['model'], $connection, $tableInfo['incremental']);
            }
        }

        // --- Execute Sync for transactional tables (Full Sync & Prune) ---
        $syncType = $this->option('type');

        if ($syncType === 'full') {
            $this->line('--- Syncing transactional tables (Full Sync & Prune) ---');
            foreach ($syncAndPruneTables as $modelName) {
                $this->call('app:sync-prune-adempiere-table', ['model' => $modelName]);
            }
        } elseif ($syncType === 'incremental') {
            $this->line('--- Syncing transactional tables (Incremental) ---');
            foreach ($syncAndPruneTables as $modelName) {
                $this->call('app:sync-incremental-adempiere-table', ['model' => $modelName]);
            }
        } else {
            $this->error('Invalid sync type specified. Use --type=full or --type=incremental.');
            return Command::FAILURE;
        }

        $this->info('Adempiere data synchronization process completed.');
        return Command::SUCCESS;
    }

    private function callSyncCommand(string $model, string $connection, bool $isIncremental)
    {
        $this->info("Calling sync for {$model} from {$connection}...");
        $params = [
            'model' => $model,
            'connection' => $connection,
        ];

        if ($isIncremental) {
            $params['--incremental'] = true;
        }

        // Using Artisan::call to run the command internally
        Artisan::call('app:sync-adempiere-table', $params);
        $this->comment(Artisan::output());
    }
}

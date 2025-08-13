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

        // Define tables and their single source connections
        $singleSourceTables = [
            'pgsql_lmp' => ['AdOrg'],
            'pgsql_sby' => ['MProductCategory', 'MProductsubcat', 'MProduct'],
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

        // --- Sync single-source tables
        foreach ($singleSourceTables as $connection => $models) {
            $this->info("--- Syncing single-source tables from {$connection} ---");
            foreach ($models as $modelName) {
                $this->info("--- Calling sync for {$modelName} from {$connection} ---");
                $this->call('app:sync-adempiere-table', [
                    'model' => $modelName,
                    '--connection' => $connection,
                    '--type' => $this->option('type'),
                ]);
                $this->info("--- Finished sync for {$modelName} ---");
                $this->line('');
            }
        }

        // --- Execute Sync for multi-source merge-only tables ---
        $this->line('--- Syncing multi-source merge-only tables ---');
        $connections = config('database.sync_connections.adempiere', []);
        if (empty($connections)) {
            $this->error('No sync connections configured in config/database.php for merge-only tables.');
        }

        foreach ($mergeOnlyTables as $tableInfo) {
            foreach ($connections as $connection) {
                $this->line("--- Processing {$tableInfo['model']} from connection: {$connection} ---");
                $this->call('app:sync-adempiere-table', [
                    'model' => $tableInfo['model'],
                    '--connection' => $connection,
                    '--type' => 'full' // Merge-only tables always run a full sync
                ]);
                $this->info("--- Finished sync for {$tableInfo['model']} from {$connection} ---");
                $this->line('');
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

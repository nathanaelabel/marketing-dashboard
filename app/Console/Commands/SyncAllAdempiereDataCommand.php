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

        // Transactional tables for fast, limited sync (temporary solution)
        $fastSyncTables = [
            'CInvoice',
            'CInvoiceline',
            'COrder',
            'COrderline',
            'CAllocationhdr',
            'CAllocationline',
        ];

        /* CATATAN SEMENTARA
        Yang sudah aman:
            'CInvoice',
            'CInvoiceline',

        Yang perlu dijalankan salah satu db saja:
            'COrder',
            'CAllocationhdr',
        */

        // --- Sync single-source tables (SKIPPED) ---
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

        // --- Execute Sync for multi-source merge-only tables (SKIPPED) ---
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

        // --- Execute Fast Sync for large transactional tables ---
        $this->line('--- Starting fast sync for large transactional tables ---');
        $allConnections = config('database.sync_connections.adempiere', []);
        if (empty($allConnections)) {
            $this->error('No sync connections configured. Aborting fast sync.');
        } else {
            foreach ($fastSyncTables as $modelName) {
                foreach ($allConnections as $connection) {
                    $this->info("--- Calling fast sync for {$modelName} from {$connection} ---");
                    $this->call('app:fast-sync-adempiere-table', [
                        'model' => $modelName,
                        '--connection' => $connection,
                    ]);
                }
                $this->info("--- Finished all fast syncs for {$modelName} ---");
                $this->line('');
            }
        }

        $this->info('Adempiere data synchronization process completed.');
        return Command::SUCCESS;
    }
}

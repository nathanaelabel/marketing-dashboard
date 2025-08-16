<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;

class SyncAllAdempiereDataCommand extends Command
{
    protected $signature = 'app:sync-all-adempiere-data 
                           {--connection= : The specific connection to process}
                           {--type=full : The type of sync to perform (full|incremental)}
                           {--skip-step1 : Skip Step 1 (single-source tables sync)}
                           {--tables= : A comma-separated list of specific models to sync}';
    protected $description = 'Orchestrates the synchronization of all Adempiere tables, optionally for a single connection.';

    public function handle()
    {
        $this->info('Starting Adempiere data synchronization process...');

        $targetConnection = $this->option('connection');
        $skipStep1 = $this->option('skip-step1');
        $specificTables = $this->option('tables') ? explode(',', $this->option('tables')) : [];
        $connectionsToProcess = $targetConnection ? explode(',', $targetConnection) : config('database.sync_connections.adempiere', []);

        if (empty($connectionsToProcess)) {
            $this->warn('No sync connections to process. Please check your configuration or command arguments.');
            return Command::FAILURE;
        }

        $singleSourceTables = ['pgsql_lmp' => ['AdOrg'], 'pgsql_sby' => ['MProductCategory', 'MProductsubcat', 'MProduct']];
        $mergeOnlyTables = [
            ['model' => 'MLocator'],
            ['model' => 'MStorage'],
            ['model' => 'MPricelistVersion'],
            ['model' => 'MProductprice'],
        ];
        $fastSyncTables = ['CInvoice', 'CInvoiceline', 'COrder', 'COrderline', 'CAllocationhdr', 'CAllocationline'];

        if (!$targetConnection && !$skipStep1) {
            $this->line("====================================================================");
            $this->info("Step 1: Processing all single-source tables first.");
            $this->line("====================================================================");
            foreach ($singleSourceTables as $connection => $models) {
                $this->info("--- Syncing from designated source: [{$connection}] ---");
                foreach ($models as $modelName) {
                    if (!empty($specificTables) && !in_array($modelName, $specificTables)) {
                        continue;
                    }
                    if (!$this->callWithRetries('app:sync-adempiere-table', ['model' => $modelName, '--connection' => $connection, '--type' => 'full'])) {
                        $this->error("Critical failure syncing single-source table {$modelName}. Aborting process.");
                        return Command::FAILURE;
                    }
                }
            }
        }

        $this->line("====================================================================");
        $this->info("Step 2: Processing remaining tables for each connection.");
        $this->line("====================================================================");

        foreach ($connectionsToProcess as $connection) {
            $this->line('');
            $this->info("Processing connection: [{$connection}]");

            if ($targetConnection && array_key_exists($connection, $singleSourceTables)) {
                $this->info("--- Syncing single-source tables for {$connection} ---");
                foreach ($singleSourceTables[$connection] as $modelName) {
                    if (!empty($specificTables) && !in_array($modelName, $specificTables)) {
                        continue;
                    }
                    if (!$this->callWithRetries('app:sync-adempiere-table', ['model' => $modelName, '--connection' => $connection, '--type' => 'full'])) {
                        continue 2; // Skip to the next connection in the outer loop
                    }
                }
            }

            $this->info("--- Syncing merge-only tables for {$connection} ---");
            foreach ($mergeOnlyTables as $tableInfo) {
                if (!empty($specificTables) && !in_array($tableInfo['model'], $specificTables)) {
                    continue;
                }
                $params = ['model' => $tableInfo['model'], '--connection' => $connection, '--type' => 'full'];
                if ($tableInfo['model'] === 'MStorage') {
                    $params['--limit'] = 50000;
                }
                if (!$this->callWithRetries('app:sync-adempiere-table', $params)) {
                    continue 2; // Skip to the next connection in the outer loop
                }
            }

            $this->info("--- Syncing fast-sync tables for {$connection} ---");
            foreach ($fastSyncTables as $modelName) {
                if (!empty($specificTables) && !in_array($modelName, $specificTables)) {
                    continue;
                }
                if (!$this->callWithRetries('app:fast-sync-adempiere-table', ['model' => $modelName, '--connection' => $connection])) {
                    continue 2; // Skip to the next connection in the outer loop
                }
            }

            $this->info("Finished processing for [{$connection}].");
            $this->line('');
        }

        if (!$targetConnection) {
            $this->info('--- Pruning records for merge-only tables after all connections are synced ---');
            foreach ($mergeOnlyTables as $tableInfo) {
                $this->call('app:prune-records', ['model' => $tableInfo['model']]);
            }
        }

        $this->info('Adempiere data synchronization process completed.');
        return Command::SUCCESS;
    }

    private function callWithRetries(string $command, array $parameters, int $retries = 3): bool
    {
        $attempts = 0;
        while ($attempts < $retries) {
            try {
                $this->call($command, $parameters);
                return true; // Success
            } catch (QueryException $e) {
                $attempts++;
                $connectionName = $parameters['--connection'] ?? 'N/A';

                if (str_contains($e->getMessage(), 'could not connect to server') || str_contains($e->getMessage(), 'Connection timed out')) {
                    if ($attempts < $retries) {
                        $this->warn("Connection to [{$connectionName}] failed. Retrying in 5 seconds... ({$attempts}/{$retries})");
                        sleep(5);
                        continue;
                    }
                    $this->error("Connection to [{$connectionName}] failed after {$retries} attempts. Skipping this connection.");
                    return false;
                } else {
                    $this->error("An unexpected SQL error on [{$connectionName}]: " . $e->getMessage());
                    return false;
                }
            }
        }
        return false;
    }
}

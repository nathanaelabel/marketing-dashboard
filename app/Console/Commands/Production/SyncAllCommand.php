<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\SyncBatch;
use App\Models\SyncProgress;

class SyncAllCommand extends Command
{
    protected $signature = 'app:sync-all
                           {--connection= : The specific connection to process}
                           {--skip-step1 : Skip Step 1 (single-source tables sync)}
                           {--tables= : A comma-separated list of specific models to sync}
                           {--resume= : Resume from a specific batch ID}';
    protected $description = 'Orchestrator for synchronizing all tables with insert/update mechanism from 17 branches';

    private ?SyncBatch $currentBatch = null;
    private ?string $batchId = null;

    public function handle()
    {
        $this->info('====================================================================');
        $this->info('Starting Data Synchronization Process');
        $this->info('====================================================================');
        $this->line('');

        $targetConnection = $this->option('connection');
        $skipStep1 = $this->option('skip-step1');
        $specificTables = $this->option('tables') ? explode(',', $this->option('tables')) : [];
        $resumeBatchId = $this->option('resume');
        $connectionsToProcess = $targetConnection ? explode(',', $targetConnection) : config('database.sync_connections.adempiere', []);

        // Initialize or resume batch
        if ($resumeBatchId) {
            $this->currentBatch = SyncBatch::find($resumeBatchId);
            if (!$this->currentBatch) {
                $this->error("Batch ID [{$resumeBatchId}] not found.");
                return Command::FAILURE;
            }
            $this->batchId = $resumeBatchId;
            $this->info("Resuming batch: {$this->batchId}");
            $this->info("Progress: {$this->currentBatch->completed_tables}/{$this->currentBatch->total_tables} tables completed");
            $this->line('');
        } else {
            $this->batchId = 'sync_' . now()->format('YmdHis') . '_' . Str::random(8);
            $this->currentBatch = SyncBatch::create([
                'batch_id' => $this->batchId,
                'status' => 'running',
                'started_at' => now(),
                'command_options' => [
                    'connection' => $targetConnection,
                    'skip_step1' => $skipStep1,
                    'tables' => $specificTables,
                ],
            ]);
            $this->info("Created new batch: {$this->batchId}");
            $this->line('');
        }

        if (empty($connectionsToProcess)) {
            $this->warn('No sync connections to process. Please check your configuration or command arguments.');
            return Command::FAILURE;
        }

        // Tables with specific source connections
        $singleSourceTables = [
            'pgsql_lmp' => ['AdOrg'],
            'pgsql_sby' => ['MProductCategory', 'MProductsubcat', 'MProduct']
        ];

        // Tables that should be synced from all branches (full sync)
        $fullSyncTables = [
            ['model' => 'MLocator'],
            ['model' => 'MStorage'],
            ['model' => 'MPricelistVersion'],
            ['model' => 'CBpartner'],
            ['model' => 'CBpartnerLocation']
        ];

        // Tables using production sync with various filtering strategies
        $productionSyncTables = [
            'MProductprice',    // Full records with m_product_id relationship
            'CInvoice',         // Date filtered (2021-01-01 to today)
            'COrder',           // Date filtered (2021-01-01 to today)
            'COrderline',       // Full records with c_order_id relationship
            'CAllocationhdr',   // Date filtered (2021-01-01 to today)
            'CAllocationline',  // Full records with c_allocationhdr_id relationship
            'MInout',           // Date filtered (2021-01-01 to today) - movementdate
            'MInoutline',       // Full records with m_inout_id relationship
            'CInvoiceline',     // Full records with c_invoice_id relationship
            'MMatchinv'         // Date filtered (2021-01-01 to today) - datetrx
        ];

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
                    $params = ['model' => $modelName, '--connection' => $connection];
                    $tempFailedSyncs = [];
                    if (!$this->callWithRetries('app:sync-table', $params, 3, $tempFailedSyncs, 'app:sync-table', $modelName)) {
                        $this->warn("Failed to sync single-source table {$modelName} after 3 attempts. Skipping and continuing...");
                        Log::warning("SyncAll: Skipping failed table in Step 1", [
                            'model' => $modelName,
                            'connection' => $connection
                        ]);
                        continue; // Skip to next table instead of aborting
                    }
                }
            }
        }

        $this->line("====================================================================");
        $this->info("Step 2: Processing remaining tables for each connection.");
        $this->line("====================================================================");

        // Track failed syncs for final retry
        $failedSyncs = [];

        foreach ($connectionsToProcess as $connection) {
            $this->line('');
            $this->info("Processing connection: [{$connection}]");
            $connectionFailedSyncs = [];

            if ($targetConnection && array_key_exists($connection, $singleSourceTables)) {
                $this->info("--- Syncing single-source tables for {$connection} ---");
                foreach ($singleSourceTables[$connection] as $modelName) {
                    if (!empty($specificTables) && !in_array($modelName, $specificTables)) {
                        continue;
                    }
                    $params = ['model' => $modelName, '--connection' => $connection];
                    if (!$this->callWithRetries('app:sync-table', $params, 3, $connectionFailedSyncs, 'app:sync-table', $modelName)) {
                        $this->warn("Failed to sync {$modelName} from {$connection}. Skipping to next table...");
                        continue; // Skip to the next table in the current connection
                    }
                }
            }

            $this->info("--- Syncing full-sync tables for {$connection} ---");
            foreach ($fullSyncTables as $tableInfo) {
                if (!empty($specificTables) && !in_array($tableInfo['model'], $specificTables)) {
                    continue;
                }
                // Use production sync for these tables
                $params = ['model' => $tableInfo['model'], '--connection' => $connection];
                if (!$this->callWithRetries('app:sync-table', $params, 3, $connectionFailedSyncs, 'app:sync-table', $tableInfo['model'])) {
                    $this->warn("Failed to sync {$tableInfo['model']} from {$connection}. Skipping to next table...");
                    continue; // Skip to the next table in the current connection
                }
            }

            $this->info("--- Syncing production-sync tables for {$connection} ---");
            foreach ($productionSyncTables as $modelName) {
                if (!empty($specificTables) && !in_array($modelName, $specificTables)) {
                    continue;
                }
                $params = ['model' => $modelName, '--connection' => $connection];
                if (!$this->callWithRetries('app:sync-table', $params, 3, $connectionFailedSyncs, 'app:sync-table', $modelName)) {
                    $this->warn("Failed to sync {$modelName} from {$connection}. Skipping to next table...");
                    continue; // Skip to the next table in the current connection
                }
            }

            // Store failed syncs for this connection
            if (!empty($connectionFailedSyncs)) {
                $failedSyncs[$connection] = $connectionFailedSyncs;
            }

            // Final retry phase for this connection
            if (!empty($connectionFailedSyncs)) {
                $this->line('');
                $this->info("====================================================================");
                $this->info("Final retry phase for connection: [{$connection}]");
                $this->info("====================================================================");

                $retrySuccessCount = 0;
                $retryFailedCount = 0;

                foreach ($connectionFailedSyncs as $failedSync) {
                    $this->line('');
                    $this->comment("Retrying: {$failedSync['command']} for model {$failedSync['model']} from connection {$connection}...");

                    $tempRetryFailures = []; // Create temp array for pass by reference
                    $success = $this->callWithRetries(
                        $failedSync['command'],
                        $failedSync['params'],
                        1, // Only 1 retry in final phase
                        $tempRetryFailures, // Pass temp array instead of null
                        $failedSync['command'],
                        $failedSync['model']
                    );

                    if ($success) {
                        $retrySuccessCount++;
                        $this->info("✓ Successfully synced {$failedSync['model']} from {$connection} on final retry.");
                        Log::info("SyncAll: Final retry success", [
                            'connection' => $connection,
                            'model' => $failedSync['model'],
                            'command' => $failedSync['command']
                        ]);
                    } else {
                        $retryFailedCount++;
                        $this->error("✗ Failed to sync {$failedSync['model']} from {$connection} on final retry.");
                        Log::error("SyncAll: Final retry failed - Manual sync required", [
                            'connection' => $connection,
                            'model' => $failedSync['model'],
                            'command' => $failedSync['command'],
                            'params' => $failedSync['params']
                        ]);
                    }
                }

                $this->line('');
                $this->info("Final retry summary for [{$connection}]: {$retrySuccessCount} succeeded, {$retryFailedCount} failed (manual sync required)");
            }

            $this->info("Finished processing for [{$connection}].");
            $this->line('');
        }

        $this->line('');
        $this->info('====================================================================');
        $this->info('Production Adempiere Data Synchronization Process Completed');
        $this->info('====================================================================');
        
        // Mark batch as completed
        $this->currentBatch->markAsCompleted();
        $this->info("Batch {$this->batchId} completed successfully.");
        $this->info("Total duration: {$this->currentBatch->duration_seconds} seconds");
        $this->info("Completed tables: {$this->currentBatch->completed_tables}/{$this->currentBatch->total_tables}");
        $this->info("Failed tables: {$this->currentBatch->failed_tables}");
        
        return Command::SUCCESS;
    }

    private function callWithRetries(
        string $command,
        array $parameters,
        int $retries = 3,
        ?array &$failedSyncs = null,
        ?string $commandName = null,
        ?string $modelName = null
    ): bool {
        $attempts = 0;
        $connectionName = $parameters['--connection'] ?? 'N/A';
        $modelName = $modelName ?? ($parameters['model'] ?? 'Unknown');

        // Check if should skip (already completed in resume mode)
        if ($this->shouldSkipTable($connectionName, $modelName)) {
            $this->comment("⏭ Skipping {$modelName} from {$connectionName} (already completed)");
            return true;
        }

        // Get or create progress entry
        $progress = $this->getOrCreateProgress($connectionName, $modelName);
        $progress->markAsStarted();

        // Add batch-id to parameters if calling sync-table command
        if ($command === 'app:sync-table' && !isset($parameters['--batch-id'])) {
            $parameters['--batch-id'] = $this->batchId;
        }

        while ($attempts < $retries) {
            try {
                $exitCode = $this->call($command, $parameters);

                // Check if command returned failure (exit code 1)
                if ($exitCode === Command::SUCCESS || $exitCode === 0) {
                    // Mark as completed (records count will be updated by SyncTableCommand)
                    $progress->markAsCompleted(0, 0);
                    $this->currentBatch->incrementCompleted();
                    return true;
                } else {
                    // Command executed but returned failure
                    $attempts++;
                    if ($attempts < $retries) {
                        $this->warn("⚠ Sync failed for table [{$modelName}] from [{$connectionName}]. Retrying in 10 seconds... (Attempt {$attempts}/{$retries})");
                        sleep(10);
                        continue;
                    } else {
                        // Track failure
                        if ($failedSyncs !== null && $commandName !== null) {
                            $failedSyncs[] = [
                                'command' => $commandName,
                                'model' => $modelName,
                                'params' => $parameters,
                                'connection' => $connectionName
                            ];
                        }
                        Log::error("SyncAll: Command failed after all retries", [
                            'command' => $commandName ?? $command,
                            'model' => $modelName,
                            'connection' => $connectionName,
                            'attempts' => $attempts
                        ]);
                        $this->error("✗ Failed to sync table [{$modelName}] from [{$connectionName}] after {$retries} attempts.");
                        $progress->markAsFailed("Command failed after {$retries} attempts");
                        $this->currentBatch->incrementFailed();
                        return false;
                    }
                }
            } catch (QueryException $e) {
                $attempts++;
                $isTimeout = $this->isConnectionTimeout($e);

                if ($isTimeout) {
                    if ($attempts < $retries) {
                        $this->warn("⚠ Connection timeout for table [{$modelName}] from [{$connectionName}]. Retrying in 10 seconds... (Attempt {$attempts}/{$retries})");
                        Log::warning("SyncAll: Connection timeout retry", [
                            'command' => $commandName ?? $command,
                            'model' => $modelName,
                            'connection' => $connectionName,
                            'attempt' => $attempts,
                            'max_retries' => $retries
                        ]);
                        sleep(10);
                        continue;
                    } else {
                        // Track failure
                        if ($failedSyncs !== null && $commandName !== null) {
                            $failedSyncs[] = [
                                'command' => $commandName,
                                'model' => $modelName,
                                'params' => $parameters,
                                'connection' => $connectionName
                            ];
                        }
                        Log::error("SyncAll: Connection timeout after all retries - Will retry at end", [
                            'command' => $commandName ?? $command,
                            'model' => $modelName,
                            'connection' => $connectionName,
                            'attempts' => $attempts,
                            'error' => $e->getMessage()
                        ]);
                        $this->error("✗ Connection timeout for table [{$modelName}] from [{$connectionName}] after {$retries} attempts.");
                        $progress->markAsFailed("Connection timeout after {$retries} attempts: " . $e->getMessage());
                        $this->currentBatch->incrementFailed();
                        return false;
                    }
                } else {
                    // Non-timeout exception
                    $this->error("✗ Unexpected SQL error for table [{$modelName}] from [{$connectionName}]: " . $e->getMessage());
                    Log::error("SyncAll: Unexpected SQL error", [
                        'command' => $commandName ?? $command,
                        'model' => $modelName,
                        'connection' => $connectionName,
                        'error' => $e->getMessage()
                    ]);
                    $progress->markAsFailed("SQL error: " . $e->getMessage());
                    $this->currentBatch->incrementFailed();
                    return false;
                }
            } catch (\Exception $e) {
                // Catch other exceptions (like command exceptions)
                $attempts++;
                $isTimeout = $this->isConnectionTimeout($e);

                if ($isTimeout && $attempts < $retries) {
                    $this->warn("⚠ Connection timeout for table [{$modelName}] from [{$connectionName}]. Retrying in 10 seconds... (Attempt {$attempts}/{$retries})");
                    Log::warning("SyncAll: Connection timeout retry (general exception)", [
                        'command' => $commandName ?? $command,
                        'model' => $modelName,
                        'connection' => $connectionName,
                        'attempt' => $attempts,
                        'max_retries' => $retries
                    ]);
                    sleep(10);
                    continue;
                } else {
                    // Track failure if timeout
                    if ($isTimeout && $failedSyncs !== null && $commandName !== null) {
                        $failedSyncs[] = [
                            'command' => $commandName,
                            'model' => $modelName,
                            'params' => $parameters,
                            'connection' => $connectionName
                        ];
                    }

                    if ($isTimeout) {
                        Log::error("SyncAll: Connection timeout after all retries - Will retry at end", [
                            'command' => $commandName ?? $command,
                            'model' => $modelName,
                            'connection' => $connectionName,
                            'attempts' => $attempts,
                            'error' => $e->getMessage()
                        ]);
                        $this->error("✗ Connection timeout for table [{$modelName}] from [{$connectionName}] after {$attempts} attempts.");
                        $progress->markAsFailed("Connection timeout: " . $e->getMessage());
                        $this->currentBatch->incrementFailed();
                    } else {
                        Log::error("SyncAll: Unexpected error", [
                            'command' => $commandName ?? $command,
                            'model' => $modelName,
                            'connection' => $connectionName,
                            'error' => $e->getMessage()
                        ]);
                        $this->error("✗ Unexpected error for table [{$modelName}] from [{$connectionName}]: " . $e->getMessage());
                        $progress->markAsFailed("Unexpected error: " . $e->getMessage());
                        $this->currentBatch->incrementFailed();
                    }
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Check if exception is a connection timeout
     */
    private function isConnectionTimeout(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $timeoutPatterns = [
            'connection timed out',
            'could not connect to server',
            'connection refused',
            'timeout',
            'connection reset',
            'no connection to the server',
            'server closed the connection'
        ];

        foreach ($timeoutPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get or create progress entry for a table sync
     */
    private function getOrCreateProgress(string $connectionName, string $modelName): SyncProgress
    {
        $modelClass = "App\\Models\\{$modelName}";
        $tableName = (new $modelClass())->getTable();

        // Check if progress entry exists for this batch
        $progress = SyncProgress::where('batch_id', $this->batchId)
            ->where('connection_name', $connectionName)
            ->where('model_name', $modelName)
            ->first();

        if (!$progress) {
            $progress = SyncProgress::create([
                'batch_id' => $this->batchId,
                'connection_name' => $connectionName,
                'table_name' => $tableName,
                'model_name' => $modelName,
                'status' => 'pending',
            ]);

            // Increment total tables in batch
            $this->currentBatch->increment('total_tables');
        }

        return $progress;
    }

    /**
     * Check if table should be skipped (already completed in resume mode)
     */
    private function shouldSkipTable(string $connectionName, string $modelName): bool
    {
        if (!$this->option('resume')) {
            return false;
        }

        $progress = SyncProgress::where('batch_id', $this->batchId)
            ->where('connection_name', $connectionName)
            ->where('model_name', $modelName)
            ->where('status', 'completed')
            ->exists();

        return $progress;
    }
}

<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use PDOException;
use App\Models\SyncProgress;

class SyncTableCommand extends Command
{
    protected $signature = 'app:sync-table {model} {--connection= : The database connection to use} {--batch-id= : The batch ID for progress tracking}';
    protected $description = 'Sync table with insert/update mechanism from 17 branches';

    public function handle()
    {
        // Explicitly disable the query log to prevent messy console output.
        DB::disableQueryLog();

        ini_set('memory_limit', '-1');
        $modelName = $this->argument('model');
        $connectionName = $this->option('connection');

        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            $this->error("Model [{$modelClass}] not found.");
            return Command::FAILURE;
        }

        // Validate that connection is provided
        if (!$connectionName) {
            $this->error("Connection name is required. Use --connection option.");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $tableName = $model->getTable();

        $this->info("Starting sync for table: {$tableName} from connection: {$connectionName}");
        $this->line('');
        Log::info("SyncTable: Starting for {$tableName} from {$connectionName}");

        try {
            $recordsProcessed = $this->runProductionSync($model, $connectionName, $tableName, $modelName);

            // Update progress if batch_id is provided
            $batchId = $this->option('batch-id');
            if ($batchId) {
                $progress = SyncProgress::where('batch_id', $batchId)
                    ->where('connection_name', $connectionName)
                    ->where('model_name', $modelName)
                    ->first();

                if ($progress) {
                    $progress->update([
                        'records_processed' => $recordsProcessed['processed'],
                        'records_skipped' => $recordsProcessed['skipped'],
                    ]);
                }
            }

            $this->info("Sync for table {$tableName} from {$connectionName} completed successfully.");
            Log::info("SyncTable: Completed for {$tableName} from {$connectionName}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            // Check if it's a connection timeout
            if ($this->isConnectionTimeout($e)) {
                Log::error("SyncTable: Connection timeout for {$tableName} from {$connectionName}", [
                    'model' => $modelName,
                    'connection' => $connectionName,
                    'table' => $tableName,
                    'error' => $e->getMessage()
                ]);
                $this->error("Connection timeout for table {$tableName} from {$connectionName}. Please retry manually.");
                return Command::FAILURE;
            }

            Log::error("Error during sync for table {$tableName}: " . $e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function runProductionSync(Model $model, string $connectionName, string $tableName, string $modelName): array
    {
        // Get the table name in lowercase, as it's returned by getTable()
        $lowerTableName = strtolower($tableName);

        // Define tables that should fetch all records
        $fullSyncTables = [
            'ad_org',
            'm_product_category',
            'm_productsubcat',
            'm_product',
            'm_locator',
            'm_storage',
            'm_pricelist_version',
            'm_productprice',
            'c_bpartner',
            'c_bpartner_location',
            'c_invoiceline',
            'c_orderline',
            'c_allocationline',
            'm_inoutline'
        ];

        // Define tables with date filtering
        $dateFilterTables = [
            'c_invoice' => 'dateinvoiced',
            'c_order' => 'dateordered',
            'c_allocationhdr' => 'datetrx',
            'm_inout' => 'movementdate',
            'm_matchinv' => 'datetrx'
        ];

        // Define tables with relationship filtering
        $relationshipFilterTables = [
            'm_productprice' => ['m_product_id'],
            'c_invoiceline' => ['c_invoice_id'],
            'c_orderline' => ['c_order_id'],
            'c_allocationline' => ['c_allocationhdr_id'],
            'm_inoutline' => ['m_inout_id']
        ];

        $query = DB::connection($connectionName)->table($tableName);

        // Apply date filtering for specific tables
        if (isset($dateFilterTables[$lowerTableName])) {
            $dateColumn = $dateFilterTables[$lowerTableName];
            $startDate = '2021-01-01 00:00:00';
            $endDate = now()->format('Y-m-d') . ' 23:59:59'; // Today's date

            $this->comment("Fetching records from {$tableName} with {$dateColumn} between {$startDate} and {$endDate}...");
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }
        // Apply relationship filtering for specific tables
        elseif (isset($relationshipFilterTables[$lowerTableName])) {
            $foreignKeys = $relationshipFilterTables[$lowerTableName];
            $this->comment("Fetching records from {$tableName} with valid relationships...");

            foreach ($foreignKeys as $foreignKey) {
                // Get the parent table name from foreign key
                $parentTable = $this->getParentTableFromForeignKey($foreignKey);
                if ($parentTable) {
                    $existingIds = $this->executeWithRetry(function () use ($parentTable, $foreignKey) {
                        return DB::table($parentTable)->pluck($foreignKey)->toArray();
                    }, $connectionName, "Fetching parent IDs from {$parentTable}");

                    // Check if we have IDs to filter by
                    if (empty($existingIds)) {
                        $this->warn("No parent records found in {$parentTable} for {$foreignKey}. Skipping {$tableName}.");
                        return ['processed' => 0, 'skipped' => 0]; // Skip this table if no parent records exist
                    }

                    // Chunk the IDs to avoid PostgreSQL parameter limit (65535)
                    // Use much smaller chunks to be safe, accounting for other query parameters
                    $chunkSize = 5000; // Very conservative chunk size
                    $idChunks = array_chunk($existingIds, $chunkSize);

                    $this->comment("Filtering {$tableName} with " . count($existingIds) . " {$foreignKey} values in " . count($idChunks) . " chunks...");

                    // Use a different approach: execute multiple queries and combine results
                    $allResults = collect();
                    foreach ($idChunks as $index => $chunk) {
                        $this->comment("Processing chunk " . ($index + 1) . " of " . count($idChunks) . " for {$tableName}...");

                        $chunkResults = $this->executeWithRetry(function () use ($connectionName, $tableName, $foreignKey, $chunk) {
                            return DB::connection($connectionName)
                                ->table($tableName)
                                ->whereIn($foreignKey, $chunk)
                                ->get();
                        }, $connectionName, "Processing chunk for {$tableName}");
                        $allResults = $allResults->concat($chunkResults);
                    }

                    // Override the main query result with our chunked results
                    $sourceData = $allResults;

                    if ($sourceData->isEmpty()) {
                        $this->info("No records found in {$tableName} from {$connectionName} with valid {$foreignKey}. Skipping.");
                        return ['processed' => 0, 'skipped' => 0];
                    }

                    $this->comment("Found {" . $sourceData->count() . "} records from chunked queries. Preparing for upsert...");
                    $this->line('');

                    // Skip to upsert since we already have our data
                    $skipNormalQuery = true;
                }
            }
        }
        // For full sync tables, fetch all records
        elseif (in_array($lowerTableName, $fullSyncTables)) {
            $this->comment("Fetching all records from {$tableName}...");
        }
        // Fallback for unmapped tables (keep original logic)
        else {
            $primaryKey = $model->getKeyName();
            $orderColumn = is_array($primaryKey) ? $primaryKey[0] : $primaryKey;
            $this->comment("Fetching the latest 10,000 records from {$tableName} ordered by {$orderColumn} DESC...");
            $query->orderBy($orderColumn, 'desc')->limit(10000);
        }

        // Execute the query only if we haven't already processed chunked results
        if (!isset($skipNormalQuery)) {
            // Execute the query with progress indication for large datasets
            if (isset($relationshipFilterTables[$lowerTableName]) || isset($dateFilterTables[$lowerTableName])) {
                $this->comment("Executing filtered query for {$tableName}...");
            }

            $sourceData = $this->executeWithRetry(function () use ($query) {
                return $query->get();
            }, $connectionName, "Executing query for {$tableName}");
        }

        // Only check if sourceData is empty if we haven't already processed chunked results
        if (!isset($skipNormalQuery)) {
            if ($sourceData->isEmpty()) {
                $this->info("No records found in {$tableName} from {$connectionName}. Skipping.");
                return ['processed' => 0, 'skipped' => 0];
            }

            $this->comment("Found {" . $sourceData->count() . "} records. Preparing for upsert...");
            $this->line('');
        }

        // Define foreign key dependencies. A table can have multiple dependencies.
        // 'optional' => true means the foreign key can be null and will be ignored if so.
        $dependencies = [
            'm_product' => [
                ['parent_table' => 'm_product_category', 'foreign_key' => 'm_product_category_id', 'optional' => true],
                ['parent_table' => 'm_productsubcat', 'foreign_key' => 'm_productsubcat_id', 'optional' => true],
            ],
            'm_locator' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'm_storage' => [
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'm_locator', 'foreign_key' => 'm_locator_id'],
            ],
            'm_pricelist_version' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'm_productprice' => [
                ['parent_table' => 'm_pricelist_version', 'foreign_key' => 'm_pricelist_version_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_bpartner_location' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_invoice' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_invoiceline' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_invoice', 'foreign_key' => 'c_invoice_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'm_inoutline', 'foreign_key' => 'm_inoutline_id', 'optional' => true],
            ],
            'c_order' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_orderline' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_order', 'foreign_key' => 'c_order_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
            ],
            'c_allocationhdr' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_allocationline' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_allocationhdr', 'foreign_key' => 'c_allocationhdr_id'],
                ['parent_table' => 'c_invoice', 'foreign_key' => 'c_invoice_id'],
            ],
            'm_inout' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_order', 'foreign_key' => 'c_order_id', 'optional' => true],
                ['parent_table' => 'c_invoice', 'foreign_key' => 'c_invoice_id', 'optional' => true],
            ],
            'm_inoutline' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'm_inout', 'foreign_key' => 'm_inout_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'c_orderline', 'foreign_key' => 'c_orderline_id', 'optional' => true],
            ],
            'm_matchinv' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_invoiceline', 'foreign_key' => 'c_invoiceline_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'm_inoutline', 'foreign_key' => 'm_inoutline_id', 'optional' => true],
            ],
        ];

        // Filter records if dependencies are defined for the current table
        if (isset($dependencies[$tableName])) {
            $this->line("Checking foreign key dependencies for {$tableName}...");
            $originalCount = $sourceData->count();

            // Pre-fetch all required parent IDs to optimize DB queries
            $existingParentIds = [];
            foreach ($dependencies[$tableName] as $dep) {
                $parentTable = $dep['parent_table'];
                $foreignKey = $dep['foreign_key'];
                if (!isset($existingParentIds[$parentTable])) {
                    $this->comment("Fetching parent IDs from {$parentTable}...");
                    $existingParentIds[$parentTable] = $this->executeWithRetry(function () use ($parentTable, $foreignKey) {
                        return DB::table($parentTable)->pluck($foreignKey)->flip();
                    }, null, "Fetching parent IDs from {$parentTable}"); // Use flip for O(1) lookups
                }
            }

            $sourceData = $sourceData->filter(function ($record) use ($dependencies, $tableName, $existingParentIds) {
                foreach ($dependencies[$tableName] as $dep) {
                    $foreignKey = $dep['foreign_key'];
                    $parentTable = $dep['parent_table'];
                    $isOptional = $dep['optional'] ?? false;
                    $fkValue = $record->$foreignKey ?? null;

                    // If the foreign key is null
                    if ($fkValue === null) {
                        // If it's optional, we can ignore it and proceed to the next dependency.
                        if ($isOptional) {
                            continue;
                        }
                        // If it's not optional, the record is invalid.
                        return false;
                    }

                    // If the foreign key has a value, it must exist in the parent table.
                    if (!isset($existingParentIds[$parentTable][$fkValue])) {
                        return false; // Skip if parent ID doesn't exist
                    }
                }
                return true; // All dependencies are satisfied
            });

            $filteredCount = $sourceData->count();
            $skippedCount = $originalCount - $filteredCount;
            if ($skippedCount > 0) {
                $this->warn("Skipped {$skippedCount} of {$originalCount} records from {$tableName} due to missing or null foreign keys.");
            }
        }

        // Get all columns that should be upserted
        $fillable = $model->getFillable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $timestampColumns = $this->getTimestampColumns($model);
        $foreignKeyColumns = [];
        if (isset($dependencies[$tableName])) {
            $foreignKeyColumns = array_map(function ($dep) {
                return $dep['foreign_key'];
            }, $dependencies[$tableName]);
        }

        // Convert all column names to lowercase for case-insensitive matching.
        $upsertColumns = array_map('strtolower', array_unique(array_merge($keyColumns, $fillable, $timestampColumns, $foreignKeyColumns)));

        $dataToUpsert = $sourceData->map(function ($row) use ($upsertColumns) {
            // Standardize source keys to lowercase to ensure case-insensitive matching.
            $lowerCaseRow = collect((array)$row)->mapWithKeys(function ($value, $key) {
                return [strtolower($key) => $value];
            });
            return $lowerCaseRow->only($upsertColumns)->all();
        })->filter()->all(); // Use filter() to remove any empty arrays that might result from the mapping.

        // Log the first processed record for debugging purposes.
        if (!empty($dataToUpsert)) {
            Log::channel('sync')->info('Sample processed data for ' . $modelName . ':', [reset($dataToUpsert)]);
        }

        // Check if this is the first sync (table is empty)
        // First sync: INSERT new data only (table is empty, so all records are new)
        // Subsequent syncs: UPSERT (INSERT new + UPDATE existing changed records)
        $isFirstSync = $this->isFirstSync($tableName);

        if ($isFirstSync) {
            // First sync: Use insertOrIgnore for better performance when table is empty
            // This will INSERT new records and ignore any duplicates (from other connections in same batch)
            $this->comment("First sync detected for {$tableName}. Using bulk INSERT (new data only)...");
            $this->executeWithRetry(function () use ($dataToUpsert, $model, $keyColumns) {
                collect($dataToUpsert)->chunk(500)->each(function ($chunk) use ($model, $keyColumns) {
                    // Use insertOrIgnore for first sync to handle potential duplicates from multiple connections
                    // This is faster than upsert when table is empty
                    try {
                        DB::table($model->getTable())->insertOrIgnore($chunk->toArray());
                    } catch (\Exception $e) {
                        // Fallback to upsert if insertOrIgnore fails (e.g., missing unique constraint)
                        $model->upsert($chunk->toArray(), $keyColumns);
                    }
                });
            }, $connectionName, "Inserting data for {$tableName}");

            $this->line(''); // Add spacing
            $this->info("Bulk INSERT complete for {$tableName} from {$connectionName}. Total records inserted: " . count($dataToUpsert));
        } else {
            // Subsequent syncs: Use upsert to automatically detect and update only changed records
            // Upsert will INSERT new records and UPDATE existing ones based on primary key
            // This ensures data parity and only processes changed records efficiently
            $this->comment("Subsequent sync detected for {$tableName}. Using UPSERT (INSERT new + UPDATE changed records)...");
            $this->executeWithRetry(function () use ($dataToUpsert, $model, $keyColumns) {
                collect($dataToUpsert)->chunk(500)->each(function ($chunk) use ($model, $keyColumns) {
                    $model->upsert($chunk->toArray(), $keyColumns);
                });
            }, $connectionName, "Upserting data for {$tableName}");

            $this->line(''); // Add spacing
            $this->info("UPSERT complete for {$tableName} from {$connectionName}. Total records processed: " . count($dataToUpsert));
        }

        // Return counts for progress tracking
        return [
            'processed' => count($dataToUpsert),
            'skipped' => isset($skippedCount) ? $skippedCount : 0,
        ];
    }

    /**
     * Get parent table name from foreign key
     */
    private function getParentTableFromForeignKey(string $foreignKey): ?string
    {
        $parentTableMap = [
            'm_product_id' => 'm_product',
            'c_invoice_id' => 'c_invoice',
            'c_order_id' => 'c_order',
            'c_allocationhdr_id' => 'c_allocationhdr',
            'm_inout_id' => 'm_inout'
        ];

        return $parentTableMap[$foreignKey] ?? null;
    }

    private function getTimestampColumns(Model $model): array
    {
        $columns = [];
        if ($model->usesTimestamps()) {
            $columns[] = $model->getCreatedAtColumn();
            $columns[] = $model->getUpdatedAtColumn();
        }
        return array_filter($columns);
    }

    /**
     * Execute a database operation with retry logic for connection timeouts
     */
    private function executeWithRetry(callable $operation, ?string $connectionName, string $operationDescription, int $maxRetries = 3): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;

                if ($this->isConnectionTimeout($e)) {
                    if ($attempts < $maxRetries) {
                        $connInfo = $connectionName ? "[{$connectionName}]" : "";
                        $this->warn("Connection timeout during {$operationDescription} {$connInfo}. Retrying in 10 seconds... ({$attempts}/{$maxRetries})");
                        Log::warning("SyncTable: Connection timeout retry", [
                            'operation' => $operationDescription,
                            'connection' => $connectionName,
                            'attempt' => $attempts,
                            'max_retries' => $maxRetries
                        ]);
                        sleep(10);
                        continue;
                    } else {
                        // Final attempt failed
                        $connInfo = $connectionName ? "[{$connectionName}]" : "";
                        $this->error("Connection timeout during {$operationDescription} {$connInfo} after {$maxRetries} attempts.");
                        Log::error("SyncTable: Connection timeout after all retries", [
                            'operation' => $operationDescription,
                            'connection' => $connectionName,
                            'attempts' => $attempts,
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }
                } else {
                    // Non-timeout exception, throw immediately
                    throw $e;
                }
            }
        }

        throw $lastException;
    }

    /**
     * Check if this is the first sync for the table (table is empty)
     */
    private function isFirstSync(string $tableName): bool
    {
        try {
            $recordCount = DB::table($tableName)->count();
            return $recordCount === 0;
        } catch (\Exception $e) {
            // If we can't check, assume it's not first sync to be safe
            Log::warning("SyncTable: Could not check if first sync for {$tableName}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
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

        // Also check for PDOException with timeout codes
        if ($e instanceof PDOException) {
            $pdoTimeoutCodes = ['08006', '08001', '08003', '08004'];
            if (in_array($e->getCode(), $pdoTimeoutCodes)) {
                return true;
            }
        }

        return false;
    }
}

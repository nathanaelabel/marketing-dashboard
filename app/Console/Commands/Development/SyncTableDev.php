<?php

namespace App\Console\Commands\Development;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use PDOException;
use App\Models\SyncProgress;

class SyncTableDev extends Command
{
    protected $signature = 'app:sync-table-dev {model} {--connection= : The database connection to use} {--batch-id= : The batch ID for progress tracking}';
    protected $description = 'Development: Sync table with insert/update mechanism from 17 branches (2025-10-01 to 2025-11-21)';

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

        if (!$connectionName) {
            $this->error("Connection name is required. Use --connection option.");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $tableName = $model->getTable();

        $this->info("Starting sync for table: {$tableName} from connection: {$connectionName} (DEVELOPMENT)");
        $this->info("Date Range: 2025-10-01 to 2025-11-21");
        $this->line('');
        Log::info("SyncTableDev: Starting for {$tableName} from {$connectionName}");

        try {
            $recordsProcessed = $this->runProductionSync($model, $connectionName, $tableName, $modelName);

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
            Log::info("SyncTableDev: Completed for {$tableName} from {$connectionName}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($this->isConnectionTimeout($e)) {
                Log::error("SyncTableDev: Connection timeout for {$tableName} from {$connectionName}", [
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
        $lowerTableName = strtolower($tableName);

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

        // Define tables with date filtering (DEVELOPMENT: 2025-10-01 to 2025-11-21)
        $dateFilterTables = [
            'c_invoice' => 'dateinvoiced',
            'c_order' => 'dateordered',
            'c_allocationhdr' => 'datetrx',
            'm_inout' => 'movementdate',
            'm_matchinv' => 'datetrx'
        ];

        $relationshipFilterTables = [
            'm_productprice' => ['m_product_id'],
            'c_invoiceline' => ['c_invoice_id'],
            'c_orderline' => ['c_order_id'],
            'c_allocationline' => ['c_allocationhdr_id'],
            'm_inoutline' => ['m_inout_id']
        ];

        $query = DB::connection($connectionName)->table($tableName);

        // Apply date filtering for specific tables (DEVELOPMENT: 2025-10-01 to 2025-11-21)
        if (isset($dateFilterTables[$lowerTableName])) {
            $dateColumn = $dateFilterTables[$lowerTableName];
            $startDate = '2025-10-01 00:00:00';
            $endDate = '2025-11-21 23:59:59';

            $this->comment("Fetching records from {$tableName} with {$dateColumn} between {$startDate} and {$endDate}...");
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        } elseif (isset($relationshipFilterTables[$lowerTableName])) {
            $foreignKeys = $relationshipFilterTables[$lowerTableName];
            $this->comment("Fetching records from {$tableName} with valid relationships...");

            foreach ($foreignKeys as $foreignKey) {
                $parentTable = $this->getParentTableFromForeignKey($foreignKey);
                if ($parentTable) {
                    $existingIds = $this->executeWithRetry(function () use ($parentTable, $foreignKey) {
                        return DB::table($parentTable)->pluck($foreignKey)->toArray();
                    }, $connectionName, "Fetching parent IDs from {$parentTable}");

                    if (empty($existingIds)) {
                        $this->warn("No parent records found in {$parentTable} for {$foreignKey}. Skipping {$tableName}.");
                        return ['processed' => 0, 'skipped' => 0];
                    }

                    // Use much smaller chunks to be safe, accounting for other query parameters
                    $chunkSize = 5000;
                    $idChunks = array_chunk($existingIds, $chunkSize);

                    $this->comment("Filtering {$tableName} with " . count($existingIds) . " {$foreignKey} values in " . count($idChunks) . " chunks...");

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

                    $sourceData = $allResults;

                    if ($sourceData->isEmpty()) {
                        $this->info("No records found in {$tableName} from {$connectionName} with valid {$foreignKey}. Skipping.");
                        return ['processed' => 0, 'skipped' => 0];
                    }

                    $this->comment("Found {" . $sourceData->count() . "} records from chunked queries. Preparing for upsert...");
                    $this->line('');

                    $skipNormalQuery = true;
                }
            }
        }
        // For full sync tables, fetch all records
        elseif (in_array($lowerTableName, $fullSyncTables)) {
            $this->comment("Fetching all records from {$tableName}...");
        } else {
            $primaryKey = $model->getKeyName();
            $orderColumn = is_array($primaryKey) ? $primaryKey[0] : $primaryKey;
            $this->comment("Fetching the latest 10,000 records from {$tableName} ordered by {$orderColumn} DESC...");
            $query->orderBy($orderColumn, 'desc')->limit(10000);
        }

        // Execute the query only if we haven't already processed chunked results
        if (!isset($skipNormalQuery)) {
            if (isset($relationshipFilterTables[$lowerTableName]) || isset($dateFilterTables[$lowerTableName])) {
                $this->comment("Executing filtered query for {$tableName}...");
            }

            $sourceData = $this->executeWithRetry(function () use ($query) {
                return $query->get();
            }, $connectionName, "Executing query for {$tableName}");
        }

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
                ['parent_table' => 'm_locator', 'foreign_key' => 'm_locator_id', 'optional' => true],
            ],
            'm_matchinv' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_invoiceline', 'foreign_key' => 'c_invoiceline_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
                ['parent_table' => 'm_inoutline', 'foreign_key' => 'm_inoutline_id', 'optional' => true],
            ],
        ];

        if (isset($dependencies[$tableName])) {
            $this->line("Checking foreign key dependencies for {$tableName}...");
            $originalCount = $sourceData->count();

            $existingParentIds = [];
            foreach ($dependencies[$tableName] as $dep) {
                $parentTable = $dep['parent_table'];
                $foreignKey = $dep['foreign_key'];
                if (!isset($existingParentIds[$parentTable])) {
                    $this->comment("Fetching parent IDs from {$parentTable}...");
                    $existingParentIds[$parentTable] = $this->executeWithRetry(function () use ($parentTable, $foreignKey) {
                        return DB::table($parentTable)->pluck($foreignKey)->flip();
                    }, null, "Fetching parent IDs from {$parentTable}");
                }
            }

            $sourceData = $sourceData->filter(function ($record) use ($dependencies, $tableName, $existingParentIds) {
                foreach ($dependencies[$tableName] as $dep) {
                    $foreignKey = $dep['foreign_key'];
                    $parentTable = $dep['parent_table'];
                    $isOptional = $dep['optional'] ?? false;
                    $fkValue = $record->$foreignKey ?? null;

                    if ($fkValue === null) {
                        if ($isOptional) {
                            continue;
                        }
                        return false;
                    }

                    if (!isset($existingParentIds[$parentTable][$fkValue])) {
                        return false;
                    }
                }
                return true;
            });

            $filteredCount = $sourceData->count();
            $skippedCount = $originalCount - $filteredCount;
            if ($skippedCount > 0) {
                $this->warn("Skipped {$skippedCount} of {$originalCount} records from {$tableName} due to missing or null foreign keys.");
            }
        }

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

        $upsertColumns = array_map('strtolower', array_unique(array_merge($keyColumns, $fillable, $timestampColumns, $foreignKeyColumns)));

        $dataToUpsert = $sourceData->map(function ($row) use ($upsertColumns) {
            $lowerCaseRow = collect((array)$row)->mapWithKeys(function ($value, $key) {
                return [strtolower($key) => $value];
            });
            return $lowerCaseRow->only($upsertColumns)->all();
        })->filter()->all();

        if (!empty($dataToUpsert)) {
            Log::channel('sync')->info('Sample processed data for ' . $modelName . ':', [reset($dataToUpsert)]);
        }

        // First sync: INSERT new data only (table is empty, so all records are new)
        // Subsequent syncs: UPSERT (INSERT new + UPDATE existing changed records)
        $isFirstSync = $this->isFirstSync($tableName);

        if ($isFirstSync) {
            // First sync: Use insertOrIgnore for better performance when table is empty
            $this->comment("First sync detected for {$tableName}. Using bulk INSERT (new data only)...");
            $this->executeWithRetry(function () use ($dataToUpsert, $model, $keyColumns) {
                collect($dataToUpsert)->chunk(500)->each(function ($chunk) use ($model, $keyColumns) {
                    try {
                        DB::table($model->getTable())->insertOrIgnore($chunk->toArray());
                    } catch (\Exception $e) {
                        $model->upsert($chunk->toArray(), $keyColumns);
                    }
                });
            }, $connectionName, "Inserting data for {$tableName}");

            $this->line('');
            $this->info("Bulk INSERT complete for {$tableName} from {$connectionName}. Total records inserted: " . count($dataToUpsert));
        } else {
            // Upsert will INSERT new records and UPDATE existing ones based on primary key
            $this->comment("Subsequent sync detected for {$tableName}. Using UPSERT (INSERT new + UPDATE changed records)...");
            $this->executeWithRetry(function () use ($dataToUpsert, $model, $keyColumns, $tableName) {
                collect($dataToUpsert)->chunk(500)->each(function ($chunk) use ($model, $keyColumns, $tableName) {
                    try {
                        $model->upsert($chunk->toArray(), $keyColumns);
                    } catch (\Exception $e) {
                        if (str_contains($e->getMessage(), 'no unique or exclusion constraint')) {
                            $this->warn("Upsert not supported for {$tableName}, using insertOrIgnore instead...");
                            DB::table($model->getTable())->insertOrIgnore($chunk->toArray());
                        } else {
                            throw $e;
                        }
                    }
                });
            }, $connectionName, "Upserting data for {$tableName}");

            $this->line('');
            $this->info("UPSERT complete for {$tableName} from {$connectionName}. Total records processed: " . count($dataToUpsert));
        }

        return [
            'processed' => count($dataToUpsert),
            'skipped' => isset($skippedCount) ? $skippedCount : 0,
        ];
    }

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
                        Log::warning("SyncTableDev: Connection timeout retry", [
                            'operation' => $operationDescription,
                            'connection' => $connectionName,
                            'attempt' => $attempts,
                            'max_retries' => $maxRetries
                        ]);
                        sleep(10);
                        continue;
                    } else {
                        $connInfo = $connectionName ? "[{$connectionName}]" : "";
                        $this->error("Connection timeout during {$operationDescription} {$connInfo} after {$maxRetries} attempts.");
                        Log::error("SyncTableDev: Connection timeout after all retries", [
                            'operation' => $operationDescription,
                            'connection' => $connectionName,
                            'attempts' => $attempts,
                            'error' => $e->getMessage()
                        ]);
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }

        throw $lastException;
    }

    private function isFirstSync(string $tableName): bool
    {
        try {
            $recordCount = DB::table($tableName)->count();
            return $recordCount === 0;
        } catch (\Exception $e) {
            // If we can't check, assume it's not first sync to be safe
            Log::warning("SyncTableDev: Could not check if first sync for {$tableName}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

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

        if ($e instanceof PDOException) {
            $pdoTimeoutCodes = ['08006', '08001', '08003', '08004'];
            if (in_array($e->getCode(), $pdoTimeoutCodes)) {
                return true;
            }
        }

        return false;
    }
}

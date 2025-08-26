<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FastSyncAdempiereTableCommand extends Command
{
    protected $signature = 'app:fast-sync-adempiere-table {model} {--connection= : The database connection to use}';
    protected $description = 'Performs a fast, insert-only sync for a large table from a single Adempiere source, limited to the latest records.';

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

        $model = new $modelClass();
        $tableName = $model->getTable();

        $this->info("Starting fast sync for table: {$tableName} from connection: {$connectionName}");
        $this->line('');
        Log::info("FastSync: Starting for {$tableName} from {$connectionName}");

        try {
            $this->runFastSync($model, $connectionName, $tableName, $modelName);

            $this->info("Fast sync for table {$tableName} from {$connectionName} completed successfully.");
            Log::info("FastSync: Completed for {$tableName} from {$connectionName}.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Error during fast sync for table {$tableName}: " . $e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function runFastSync(Model $model, string $connectionName, string $tableName, string $modelName)
    {
        // Get the table name in lowercase, as it's returned by getTable()
        $lowerTableName = strtolower($tableName);
        
        // Define tables that should fetch all records
        $fullSyncTables = [
            'ad_org', 'm_product_category', 'm_productsubcat', 'm_product',
            'm_locator', 'm_storage', 'm_pricelist_version', 'm_productprice',
            'c_invoiceline', 'c_orderline', 'c_allocationline'
        ];
        
        // Define tables with date filtering
        $dateFilterTables = [
            'c_invoice' => 'dateinvoiced',
            'c_order' => 'dateordered', 
            'c_allocationhdr' => 'datetrx'
        ];
        
        // Define tables with relationship filtering
        $relationshipFilterTables = [
            'm_productprice' => ['m_product_id'],
            'c_invoiceline' => ['c_invoice_id'],
            'c_orderline' => ['c_order_id'],
            'c_allocationline' => ['c_allocationhdr_id']
        ];

        $query = DB::connection($connectionName)->table($tableName);
        
        // Apply date filtering for specific tables
        if (isset($dateFilterTables[$lowerTableName])) {
            $dateColumn = $dateFilterTables[$lowerTableName];
            $startDate = '2024-01-01 00:00:00';
            $endDate = '2025-08-23 00:00:00';
            
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
                    $existingIds = DB::table($parentTable)->pluck($foreignKey);
                    $query->whereIn($foreignKey, $existingIds);
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

        $sourceData = $query->get();

        if ($sourceData->isEmpty()) {
            $this->info("No records found in {$tableName} from {$connectionName}. Skipping.");
            return;
        }

        $this->comment("Found {" . $sourceData->count() . "} records. Preparing for insertion...");
        $this->line('');

        // Define foreign key dependencies. A table can have multiple dependencies.
        // 'optional' => true means the foreign key can be null and will be ignored if so.
        $dependencies = [
            'm_product' => [
                ['parent_table' => 'm_product_category', 'foreign_key' => 'm_product_category_id', 'optional' => true],
                ['parent_table' => 'm_productsubcat', 'foreign_key' => 'm_product_subcat_id', 'optional' => true],
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
            'c_invoice' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
            ],
            'c_invoiceline' => [
                ['parent_table' => 'ad_org', 'foreign_key' => 'ad_org_id'],
                ['parent_table' => 'c_invoice', 'foreign_key' => 'c_invoice_id'],
                ['parent_table' => 'm_product', 'foreign_key' => 'm_product_id'],
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
                    $existingParentIds[$parentTable] = DB::table($parentTable)->pluck($foreignKey)->flip(); // Use flip for O(1) lookups
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

        // Get all columns that should be inserted
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
        $insertColumns = array_map('strtolower', array_unique(array_merge($keyColumns, $fillable, $timestampColumns, $foreignKeyColumns)));

        $dataToInsert = $sourceData->map(function ($row) use ($insertColumns) {
            // Standardize source keys to lowercase to ensure case-insensitive matching.
            $lowerCaseRow = collect((array)$row)->mapWithKeys(function ($value, $key) {
                return [strtolower($key) => $value];
            });
            return $lowerCaseRow->only($insertColumns)->all();
        })->filter()->all(); // Use filter() to remove any empty arrays that might result from the mapping.

        // Log the first processed record for debugging purposes.
        if (!empty($dataToInsert)) {
            Log::channel('sync')->info('Sample processed data for ' . $modelName . ':', [reset($dataToInsert)]);
        }

        // Chunk the data to avoid hitting the parameter limit in PostgreSQL.
        collect($dataToInsert)->chunk(500)->each(function ($chunk) use ($model) {
            $model->insertOrIgnore($chunk->toArray());
        });

        $this->line(''); // Add spacing
        $this->info("Insertion attempt complete for {$tableName} from {$connectionName}.");
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
            'c_allocationhdr_id' => 'c_allocationhdr'
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
}

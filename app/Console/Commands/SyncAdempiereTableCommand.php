<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\MStorage;

class SyncAdempiereTableCommand extends Command
{
    protected $signature = 'app:sync-adempiere-table {model} {--connection=} {--type=full} {--limit=}';
    protected $description = 'Synchronize a single Adempiere table to the local database.';

    public function handle()
    {
        ini_set('memory_limit', '-1');
        $modelName = $this->argument('model');
        $connectionName = $this->option('connection');
        $syncType = $this->option('type');

        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            $this->error("Model [{$modelClass}] not found.");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $tableName = $model->getTable();
        $timestampFile = storage_path("app/sync_{$tableName}_timestamp.txt");

        $this->info("Starting sync for table: {$tableName} from connection: {$connectionName}");
        $this->line('');
        Log::info("SyncAdempiereTableCommand: Starting sync for {$tableName}");

        try {
            if ($syncType === 'incremental' && $this->isTimestamped($model)) {
                $this->runIncrementalSync($model, $connectionName, $timestampFile);
            } else {
                $this->runFullSync($model, $connectionName);
            }

            $this->line('');
            $this->info("Synchronization for table {$tableName} completed successfully.");
            Log::info("SyncAdempiereTableCommand: Sync for {$tableName} completed.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Error during synchronization for table {$tableName}: " . $e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred: ' . $e->getMessage());

            // Re-throw the exception so the orchestrator (SyncAllAdempiereDataCommand) can catch it and handle retries.
            throw $e;
        }
    }

    private function isTimestamped($model)
    {
        return defined(get_class($model) . '::CREATED_AT') && defined(get_class($model) . '::UPDATED_AT');
    }

    private function runFullSync($model, $connectionName)
    {
        // Define foreign key dependencies for validation.
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

        $this->info('Running full sync (merge/upsert mode) using manual chunking...');
        $fillable = $model->getFillable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $columns = array_unique(array_merge($keyColumns, $fillable));
        $processedCount = 0;
        $chunkSize = 2000;
        $limit = $this->option('limit');
        $query = DB::connection($connectionName)->table($model->getTable())->select($columns);

        if ($limit && is_numeric($limit) && $limit > 0) {
            $this->info("Applying a limit of {$limit} rows to the query.");
            $query->limit($limit);
        }

        $page = 1;
        do {
            $records = $query->forPage($page, $chunkSize)->get();

            if ($records->isEmpty()) {
                break;
            }

            $currentTable = $model->getTable();
            $validRecords = $records;

            if (isset($dependencies[$currentTable])) {
                $allParentKeys = [];
                foreach ($dependencies[$currentTable] as $dep) {
                    $parentTable = $dep['parent_table'];
                    $foreignKey = $dep['foreign_key'];
                    $fkValues = $records->pluck($foreignKey)->filter()->unique()->all();

                    if (!empty($fkValues)) {
                        $parentModelClass = "App\\Models\\" . Str::studly(Str::singular($parentTable));
                        if (class_exists($parentModelClass)) {
                            $parentModel = new $parentModelClass();
                            $parentKeyName = $parentModel->getKeyName();
                            $allParentKeys[$foreignKey] = DB::table($parentTable)->whereIn($parentKeyName, $fkValues)->pluck($parentKeyName);
                        } else {
                            $allParentKeys[$foreignKey] = DB::table($parentTable)->whereIn('id', $fkValues)->pluck('id');
                        }
                    }
                }

                $validRecords = $records->filter(function ($row) use ($dependencies, $currentTable, $allParentKeys) {
                    foreach ($dependencies[$currentTable] as $dep) {
                        $foreignKey = $dep['foreign_key'];
                        $isOptional = $dep['optional'] ?? false;
                        $fkValue = $row->{$foreignKey} ?? null;

                        if ($fkValue === null) {
                            if ($isOptional) continue;
                            return false;
                        }

                        if (!isset($allParentKeys[$foreignKey]) || !$allParentKeys[$foreignKey]->contains($fkValue)) {
                            return false;
                        }
                    }
                    return true;
                });
            }

            if ($validRecords->count() < $records->count()) {
                $skipped = $records->count() - $validRecords->count();
                $this->warn("{$skipped} records in this chunk were skipped due to invalid foreign keys.");
            }

            if ($validRecords->isNotEmpty()) {
                if ($model instanceof MStorage) {
                    $keys = $validRecords->map(function ($row) {
                        return ['m_product_id' => $row->m_product_id, 'm_locator_id' => $row->m_locator_id];
                    })->unique()->all();

                    $existingStorage = $model->whereInMultiple(['m_product_id', 'm_locator_id'], $keys)->get()->keyBy(function ($item) {
                        return $item->m_product_id . '-' . $item->m_locator_id;
                    });

                    $upsertData = [];
                    foreach ($validRecords as $row) {
                        $key = $row->m_product_id . '-' . $row->m_locator_id;
                        $data = collect((array)$row)->only($fillable)->toArray();

                        if (isset($upsertData[$key])) {
                            $upsertData[$key]['qtyonhand'] += $data['qtyonhand'];
                        } else {
                            if ($existingStorage->has($key)) {
                                $data['qtyonhand'] += $existingStorage->get($key)->qtyonhand;
                            }
                            $upsertData[$key] = $data;
                        }
                    }

                    $model->upsert(
                        array_values($upsertData),
                        ['m_product_id', 'm_locator_id'],
                        ['qtyonhand']
                    );
                } else {
                    DB::transaction(function () use ($model, $validRecords, $keyColumns, $fillable) {
                        foreach ($validRecords as $row) {
                            $condition = [];
                            foreach ($keyColumns as $key) {
                                if (isset($row->{$key})) {
                                    $condition[$key] = $row->{$key};
                                }
                            }
                            if (empty($condition)) continue;

                            $dataToUpdate = collect((array)$row)->only($fillable)->toArray();
                            $model->updateOrInsert($condition, $dataToUpdate);
                        }
                    });
                }
            }

            $processedCount += $records->count();
            $this->info("Processed {$processedCount} records (including skipped)...");
            $page++;

            if ($limit && is_numeric($limit) && $processedCount >= $limit) {
                $this->info("Reached the specified limit of {$limit} records. Stopping sync for this table.");
                break;
            }
        } while ($records->count() === $chunkSize);

        $this->info("Full sync completed. Total {$processedCount} records processed.");
    }

    private function runIncrementalSync($model, $connectionName, $timestampFile)
    {
        $this->info('Running incremental sync...');
        $syncStartTime = now();
        $lastSyncTimestamp = file_exists($timestampFile) ? file_get_contents($timestampFile) : '1970-01-01 00:00:00';

        $this->info("Checking for updates since: {$lastSyncTimestamp}");

        $fillable = $model->getFillable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $columns = array_unique(array_merge($keyColumns, $fillable, [$model::CREATED_AT, $model::UPDATED_AT]));

        $updatedData = DB::connection($connectionName)
            ->table($model->getTable())
            ->select($columns)
            ->where($model::UPDATED_AT, '>=', $lastSyncTimestamp)
            ->get();

        if ($updatedData->isEmpty()) {
            $this->info('No new updates found.');
            file_put_contents($timestampFile, $syncStartTime->toDateTimeString()); // Still update timestamp
            return;
        }

        $this->info($updatedData->count() . ' records to update/insert.');

        DB::transaction(function () use ($model, $updatedData, $fillable) {
            $updateColumns = array_merge($fillable, [$model::CREATED_AT, $model::UPDATED_AT]);
            foreach ($updatedData as $row) {
                $dataToUpdate = collect($row)->only($updateColumns)->toArray();

                // Build the condition array for updateOrInsert, handling composite keys
                $condition = [];
                $primaryKey = $model->getKeyName();
                $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
                foreach ($keyColumns as $key) {
                    $condition[$key] = $row->{$key};
                }

                $model->updateOrInsert($condition, $dataToUpdate);
            }
        });

        file_put_contents($timestampFile, $syncStartTime->toDateTimeString());
        $this->info('Incremental sync completed.');
    }
}

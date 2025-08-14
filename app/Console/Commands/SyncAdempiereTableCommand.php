<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AdOrg;
use App\Models\MProduct;
use App\Models\MProductCategory;
use App\Models\MProductsubcat;
use App\Models\MStorage;
use App\Models\MLocator;
use App\Models\CInvoice;
use App\Models\CInvoiceline;
use App\Models\COrder;
use App\Models\COrderline;
use App\Models\CAllocationhdr;
use App\Models\CAllocationline;

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

            if ($model instanceof \App\Models\MStorage) {
                // For MStorage, we cannot do a simple upsert as we need to aggregate quantities.
                // This logic is complex and requires careful handling of existing data.
                // The most robust way is to fetch existing records that match the incoming keys,
                // aggregate in memory, and then perform a final upsert.

                $keys = $records->map(function ($row) {
                    return ['m_product_id' => $row->m_product_id, 'm_locator_id' => $row->m_locator_id];
                })->unique()->all();

                // Fetch all existing storage data for the keys in the current chunk
                $existingStorage = $model->whereInMultiple(['m_product_id', 'm_locator_id'], $keys)->get()->keyBy(function ($item) {
                    return $item->m_product_id . '-' . $item->m_locator_id;
                });

                // Aggregate new data from the source chunk
                $upsertData = [];
                foreach ($records as $row) {
                    $key = $row->m_product_id . '-' . $row->m_locator_id;
                    $data = collect((array)$row)->only($fillable)->toArray();

                    if (isset($upsertData[$key])) {
                        // Aggregate within the current chunk
                        $upsertData[$key]['qtyonhand'] += $data['qtyonhand'];
                    } else {
                        // If it's a new entry in the chunk, check against existing DB data
                        if ($existingStorage->has($key)) {
                            // It exists in DB, so add source qty to existing qty
                            $data['qtyonhand'] += $existingStorage->get($key)->qtyonhand;
                        }
                        $upsertData[$key] = $data;
                    }
                }

                // Perform a single, powerful upsert operation
                $model->upsert(
                    array_values($upsertData),
                    ['m_product_id', 'm_locator_id'], // Unique by columns
                    ['qtyonhand'] // Columns to update if record exists
                );
            } else {
                // Default behavior for all other models
                DB::transaction(function () use ($model, $records, $keyColumns, $fillable) {
                    foreach ($records as $row) {
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

            $processedCount += $records->count();
            $this->info("Processed {$processedCount} records...");
            $page++;

            // Stop processing if the specified limit has been reached or exceeded.
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

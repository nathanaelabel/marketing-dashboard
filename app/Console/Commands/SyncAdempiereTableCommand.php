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
    protected $signature = 'app:sync-adempiere-table {model} {connection} {--incremental}';
    protected $description = 'Synchronize a single Adempiere table to the local database.';

    public function handle()
    {
        ini_set('memory_limit', '-1');
        $modelName = $this->argument('model');
        $connectionName = $this->argument('connection');
        $isIncremental = $this->option('incremental');

        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            $this->error("Model [{$modelClass}] not found.");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $tableName = $model->getTable();
        $timestampFile = storage_path("app/sync_{$tableName}_timestamp.txt");

        $this->info("Starting sync for table: {$tableName} from connection: {$connectionName}");
        Log::info("SyncAdempiereTableCommand: Starting sync for {$tableName}");

        try {
            if ($isIncremental && $this->isTimestamped($model)) {
                $this->runIncrementalSync($model, $connectionName, $timestampFile);
            } else {
                $this->runFullSync($model, $connectionName);
            }

            $this->info("Synchronization for table {$tableName} completed successfully.");
            Log::info("SyncAdempiereTableCommand: Sync for {$tableName} completed.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Error during synchronization for table {$tableName}: " . $e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isTimestamped($model)
    {
        return defined(get_class($model) . '::CREATED_AT') && defined(get_class($model) . '::UPDATED_AT');
    }

    private function runFullSync($model, $connectionName)
    {
        $this->info('Running full sync (merge/upsert mode) using lazy chunking...');
        $fillable = $model->getFillable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $columns = array_unique(array_merge($keyColumns, $fillable));
        $processedCount = 0;

        $query = DB::connection($connectionName)->table($model->getTable())->select($columns);

        // Use lazy() for memory efficiency, then chunk the result for processing.
        // This works for all primary key types, including composite keys.
        foreach ($query->lazy(2000)->chunk(2000) as $records) {
            DB::transaction(function () use ($records, $model, $keyColumns, $fillable) {
                foreach ($records as $row) {
                    $condition = [];
                    foreach ($keyColumns as $key) {
                        // Ensure we don't try to access a property on a non-object
                        if (is_object($row) && property_exists($row, $key)) {
                            $condition[$key] = $row->{$key};
                        } else {
                            // Handle cases where the key might not exist, though this is unlikely
                            // with a proper select. You might want to log this.
                            continue;
                        }
                    }
                    $dataToUpdate = collect((array)$row)->only($fillable)->toArray();
                    $model->updateOrInsert($condition, $dataToUpdate);
                }
            });
            $processedCount += $records->count();
            $this->info("Processed {$processedCount} records...");
        }

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

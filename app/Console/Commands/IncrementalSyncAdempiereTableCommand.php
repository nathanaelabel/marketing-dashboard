<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IncrementalSyncAdempiereTableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-incremental-adempiere-table {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Incrementally syncs an Adempiere table from multiple sources based on a timestamp, processing only new or updated records.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');

        $modelName = $this->argument('model');
        $modelClass = "App\\Models\\" . $modelName;

        if (!class_exists($modelClass)) {
            $this->error("Model [{$modelName}] not found.");
            return Command::FAILURE;
        }

        $model = new $modelClass();
        $tableName = $model->getTable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $createdAtColumn = $model::CREATED_AT;
        $updatedAtColumn = $model::UPDATED_AT;

        $this->info("--- Starting Incremental Sync for {$tableName} ---");
        Log::info("IncrementalSync: Starting process for {$tableName}.");

        $timestampFile = storage_path("app/sync-timestamp-{$tableName}.txt");
        $syncStartTime = now();

        try {
            $lastSyncTimestamp = $this->getLastSyncTimestamp($timestampFile);
            $this->info("Checking for updates since: {$lastSyncTimestamp}");

            $connections = config('database.sync_connections.adempiere', []);
            $totalProcessed = 0;

            foreach ($connections as $connection) {
                $this->info("Processing connection: {$connection}");
                $processedCount = 0;

                $sourceQuery = DB::connection($connection)->table($tableName)
                    ->where($updatedAtColumn, '>', $lastSyncTimestamp)
                    ->orWhere($createdAtColumn, '>', $lastSyncTimestamp);

                $processChunk = function ($sourceData) use ($model, $keyColumns, &$processedCount, $connection) {
                    if ($sourceData->isEmpty()) {
                        return;
                    }
                    $fillable = $model->getFillable();
                    $dataToUpsert = [];

                    foreach ($sourceData as $row) {
                        $dataToUpsert[] = collect((array)$row)->only($fillable)->toArray();
                    }

                    if (!empty($dataToUpsert)) {
                        DB::transaction(function () use ($model, $dataToUpsert, $keyColumns) {
                            $model->upsert($dataToUpsert, $keyColumns);
                        });
                        $processedCount += count($dataToUpsert);
                    }
                };

                if (count($keyColumns) === 1) {
                    $sourceQuery->chunkById(500, $processChunk, $keyColumns[0]);
                } else {
                    foreach ($keyColumns as $key) {
                        $sourceQuery->orderBy($key);
                    }
                    $sourceQuery->chunk(500, $processChunk);
                }

                if ($processedCount > 0) {
                    $this->info("Processed {$processedCount} new/updated records from {$connection}.");
                    $totalProcessed += $processedCount;
                }
            }

            if ($totalProcessed > 0) {
                $this->updateLastSyncTimestamp($timestampFile, $syncStartTime);
                $this->info("Incremental sync finished. Total records processed: {$totalProcessed}.");
            } else {
                $this->info('No new records to sync.');
            }
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            Log::error("IncrementalSync Error for {$tableName}: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("--- Incremental Sync for {$tableName} completed successfully! ---");
        return Command::SUCCESS;
    }

    private function getLastSyncTimestamp($file)
    {
        if (!file_exists($file)) {
            return Carbon::parse('2000-01-01 00:00:00')->toDateTimeString();
        }
        return trim(file_get_contents($file));
    }

    private function updateLastSyncTimestamp($file, Carbon $timestamp)
    {
        file_put_contents($file, $timestamp->toDateTimeString());
        $this->info("Timestamp updated to: {$timestamp->toDateTimeString()}");
    }
}

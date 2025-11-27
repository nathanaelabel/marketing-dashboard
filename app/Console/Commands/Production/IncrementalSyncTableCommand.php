<?php

namespace App\Console\Commands\Production;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PDOException;

class IncrementalSyncTableCommand extends Command
{
    protected $signature = 'app:incremental-sync-table {model}';
    protected $description = 'Incrementally syncs table from 17 branches based on timestamp, processing only new or updated records';

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

        $timestampFile = storage_path("app/production-sync-timestamp-{$tableName}.txt");
        $syncStartTime = now();

        try {
            $lastSyncTimestamp = $this->getLastSyncTimestamp($timestampFile);
            $this->info("Checking for updates since: {$lastSyncTimestamp}");

            $connections = config('database.sync_connections.adempiere', []);
            $totalProcessed = 0;

            foreach ($connections as $connection) {
                $this->info("Processing connection: {$connection}");
                $processedCount = 0;

                try {
                    $sourceQuery = DB::connection($connection)->table($tableName)
                        ->where(function ($query) use ($updatedAtColumn, $createdAtColumn, $lastSyncTimestamp) {
                            $query->where($updatedAtColumn, '>', $lastSyncTimestamp)
                                ->orWhere($createdAtColumn, '>', $lastSyncTimestamp);
                        });

                    $processChunk = function ($sourceData) use ($model, $keyColumns, &$processedCount, $connection, $tableName) {
                        if ($sourceData->isEmpty()) {
                            return;
                        }
                        $fillable = $model->getFillable();
                        $dataToUpsert = [];

                        foreach ($sourceData as $row) {
                            $dataToUpsert[] = collect((array)$row)->only($fillable)->toArray();
                        }

                        if (!empty($dataToUpsert)) {
                            $this->executeWithRetry(function () use ($model, $dataToUpsert, $keyColumns) {
                                DB::transaction(function () use ($model, $dataToUpsert, $keyColumns) {
                                    $model->upsert($dataToUpsert, $keyColumns);
                                });
                            }, $connection, "Upserting incremental data for {$tableName}");
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
                } catch (\Exception $e) {
                    if ($this->isConnectionTimeout($e)) {
                        $this->warn("Connection timeout for {$connection}. Skipping this connection.");
                        Log::warning("IncrementalSync: Connection timeout", [
                            'table' => $tableName,
                            'connection' => $connection,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    } else {
                        throw $e;
                    }
                }
            }

            if ($totalProcessed > 0) {
                $this->updateLastSyncTimestamp($timestampFile, $syncStartTime);
                $this->info("Incremental sync finished. Total records processed: {$totalProcessed}.");
                Log::info("IncrementalSync: Completed for {$tableName}", [
                    'total_processed' => $totalProcessed
                ]);
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
            return Carbon::parse('2021-01-01 00:00:00')->toDateTimeString();
        }
        return trim(file_get_contents($file));
    }

    private function updateLastSyncTimestamp($file, Carbon $timestamp)
    {
        file_put_contents($file, $timestamp->toDateTimeString());
        $this->info("Timestamp updated to: {$timestamp->toDateTimeString()}");
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
                        Log::warning("IncrementalSync: Connection timeout retry", [
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
                        Log::error("IncrementalSync: Connection timeout after all retries", [
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

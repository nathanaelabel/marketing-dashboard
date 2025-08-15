<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAndPruneAdempiereTableCommand extends Command
{
    protected $signature = 'app:sync-prune-adempiere-table {model}';
    protected $description = 'Syncs a single Adempiere table from multiple sources using an upsert method, then prunes records that no longer exist in any source.';

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

        $this->info("--- Starting Sync & Prune for {$tableName} ---");
        Log::info("SyncAndPrune: Starting process for {$tableName}.");

        $stateFilePath = storage_path("app/sync-state-{$tableName}.json");

        $connections = config('database.sync_connections.adempiere', []);
        if (empty($connections)) {
            $this->error('No sync connections configured in config/database.php');
            return Command::FAILURE;
        }

        // Step 1: Collect all valid primary keys from all sources efficiently, with resume capability
        $this->line('Step 1: Collecting all valid primary keys from all sources...');

        $validKeySet = collect();
        $startConnectionIndex = 0;

        if (file_exists($stateFilePath)) {
            $this->info('Resuming from previous state...');
            $state = json_decode(file_get_contents($stateFilePath), true);
            $validKeySet = collect($state['keys'] ?? []);
            $startConnectionIndex = ($state['last_connection_index'] ?? -1) + 1;
            $this->info("Resuming from connection index: {$startConnectionIndex}. Keys collected so far: " . $validKeySet->count());
        }

        for ($i = $startConnectionIndex; $i < count($connections); $i++) {
            $connection = $connections[$i];
            $this->info("Fetching keys from {$connection} (Connection " . ($i + 1) . " of " . count($connections) . ")...");

            try {
                $query = DB::connection($connection)->table($tableName)->select($keyColumns);
                foreach ($keyColumns as $keyColumn) {
                    $query->orderBy($keyColumn);
                }

                foreach ($query->lazy(2000) as $record) {
                    $keyString = (count($keyColumns) === 1)
                        ? $record->{$keyColumns[0]}
                        : implode('-', array_map(fn($k) => $record->{$k}, $keyColumns));

                    $validKeySet[$keyString] = true;

                    if ($validKeySet->count() % 10000 === 0) {
                        $this->comment('Collected ' . $validKeySet->count() . ' unique keys so far...');
                    }
                }

                // Save state after successfully processing a connection
                file_put_contents($stateFilePath, json_encode(['last_connection_index' => $i, 'keys' => $validKeySet->all()]));
                $this->info("Successfully processed {$connection}. State saved.");
            } catch (\Illuminate\Database\QueryException $e) {
                $this->error("Failed to connect or query {$connection}. Error: " . $e->getMessage());
                $this->error("Please check the connection and restart the command. Progress has been saved.");
                return Command::FAILURE;
            }
        }
        $this->info("Found {$validKeySet->count()} unique valid records across all sources.");

        // Step 2: Sync data from all sources (Update or Insert)
        $this->line('Step 2: Syncing data from all sources (Upsert)...');
        $fillable = $model->getFillable();
        $columns = array_unique(array_merge($keyColumns, $fillable, $this->getTimestampColumns($model)));

        foreach ($connections as $connection) {
            $this->info("Syncing from {$connection}...");
            $processedCount = 0;

            $sourceQuery = DB::connection($connection)->table($tableName)->select($columns);

            $processChunk = function ($sourceData) use ($model, $tableName, $keyColumns, $fillable, &$processedCount, $connection) {
                DB::transaction(function () use ($model, $sourceData, $keyColumns, $fillable, $connection, $tableName) {
                    foreach ($sourceData as $row) {
                        $condition = [];
                        $hasNullKey = false;
                        foreach ($keyColumns as $key) {
                            if (is_null($row->{$key})) {
                                $hasNullKey = true;
                                break;
                            }
                            $condition[$key] = $row->{$key};
                        }

                        // Also check other critical non-nullable foreign keys, like m_product_id for c_invoiceline
                        if ($tableName === 'c_invoiceline' && is_null($row->m_product_id)) {
                            $hasNullKey = true;
                        }

                        if ($hasNullKey) {
                            $this->warn("Skipping row in {$tableName} from {$connection} due to NULL in a required key column. Data: " . json_encode($row));
                            continue;
                        }

                        $dataToUpdate = collect((array)$row)->only($fillable)->toArray();
                        $model->updateOrInsert($condition, $dataToUpdate);
                    }
                });
                $processedCount += $sourceData->count();
                $this->comment("Processed {$processedCount} records from {$connection}...");
            };

            if (count($keyColumns) === 1) {
                $sourceQuery->chunkById(1000, $processChunk, $keyColumns[0]);
            } else {
                foreach ($keyColumns as $key) {
                    $sourceQuery->orderBy($key);
                }
                $sourceQuery->chunk(1000, $processChunk);
            }

            if ($processedCount > 0) {
                $this->info("Finished processing {$processedCount} records from {$connection}.");
            } else {
                $this->warn("No data found in {$connection}. Skipping.");
            }
        }

        // Step 3: Prune (delete) records that are no longer valid
        $this->line('Step 3: Pruning old or invalid records from local database...');
        $totalDeleted = 0;

        $localQuery = DB::table($tableName)->select($keyColumns);

        $pruningCallback = function ($localKeys) use (&$totalDeleted, $validKeySet, $keyColumns, $tableName) {
            $this->processPruningChunk($localKeys, $totalDeleted, $validKeySet, $keyColumns, $tableName);
        };

        if (count($keyColumns) === 1) {
            $localQuery->chunkById(2000, $pruningCallback, $keyColumns[0]);
        } else {
            foreach ($keyColumns as $key) {
                $localQuery->orderBy($key);
            }
            $localQuery->chunk(2000, $pruningCallback);
        }

        if ($totalDeleted > 0) {
            $this->info("Pruned {$totalDeleted} old records.");
            Log::info("SyncAndPrune: Pruned {$totalDeleted} records from {$tableName}.");
        } else {
            $this->info('No records needed pruning.');
        }

        // Clean up the state file upon successful completion
        if (file_exists($stateFilePath)) {
            unlink($stateFilePath);
        }

        $this->info("--- Sync & Prune for {$tableName} completed successfully! ---");
        Log::info("SyncAndPrune: Process for {$tableName} completed.");

        return Command::SUCCESS;
    }

    private function processPruningChunk($localKeys, &$totalDeleted, $validKeySet, $keyColumns, $tableName)
    {
        $chunkDeleted = 0;
        DB::transaction(function () use ($localKeys, &$chunkDeleted, $validKeySet, $keyColumns, $tableName) {
            foreach ($localKeys as $localKey) {
                if (count($keyColumns) === 1) {
                    $localKeyString = $localKey->{$keyColumns[0]};
                } else {
                    $localKeyString = implode('-', array_map(fn($key) => $localKey->{$key}, $keyColumns));
                }

                if (!isset($validKeySet[$localKeyString])) {
                    DB::table($tableName)->where((array)$localKey)->delete();
                    $chunkDeleted++;
                }
            }
        });
        if ($chunkDeleted > 0) {
            $totalDeleted += $chunkDeleted;
            $this->comment("Pruned {$totalDeleted} records so far...");
        }
    }

    private function getTimestampColumns($model)
    {
        $timestamps = [];
        if ($model->usesTimestamps()) {
            $timestamps[] = $model->getCreatedAtColumn();
            $timestamps[] = $model->getUpdatedAtColumn();
        }
        return $timestamps;
    }
}

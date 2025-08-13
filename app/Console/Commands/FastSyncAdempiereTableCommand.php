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
        // Define a map for table-specific ordering columns to get the 'latest' records.
        $orderColumnMap = [
            'c_invoice' => 'dateinvoiced',
            'c_order' => 'dateordered',
            'c_allocationhdr' => 'datetrx',
            'c_invoiceline' => 'c_invoiceline_id',
            'c_orderline' => 'c_orderline_id',
            'c_allocationline' => 'c_allocationline_id',
        ];

        // Get the table name in lowercase, as it's returned by getTable()
        $lowerTableName = strtolower($tableName);
        $orderColumn = $orderColumnMap[$lowerTableName] ?? null;

        if (!$orderColumn) {
            // Fallback to primary key if no specific column is mapped.
            $primaryKey = $model->getKeyName();
            if (is_array($primaryKey)) {
                $this->warn("Model [{$tableName}] is not mapped and has a composite key. Using the first key: {$primaryKey[0]}");
                $orderColumn = $primaryKey[0];
            } else {
                $this->warn("Model [{$tableName}] is not mapped. Falling back to primary key: {$primaryKey}");
                $orderColumn = $primaryKey;
            }
        }

        $this->comment("Fetching the latest 10,000 records from {$tableName} ordered by {$orderColumn} DESC...");

        $sourceData = DB::connection($connectionName)
            ->table($tableName)
            ->orderBy($orderColumn, 'desc')
            ->limit(10000)
            ->get();

        if ($sourceData->isEmpty()) {
            $this->info("No records found in {$tableName} from {$connectionName}. Skipping.");
            return;
        }

        $this->comment("Found {" . $sourceData->count() . "} records. Preparing for insertion...");

        // Get all columns that should be inserted
        $fillable = $model->getFillable();
        $primaryKey = $model->getKeyName();
        $keyColumns = is_array($primaryKey) ? $primaryKey : [$primaryKey];
        $timestampColumns = $this->getTimestampColumns($model);
        $insertColumns = array_unique(array_merge($keyColumns, $fillable, $timestampColumns));

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

        $this->info("Insertion attempt complete for {$tableName} from {$connectionName}.");
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

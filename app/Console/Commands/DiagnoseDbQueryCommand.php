<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnoseDbQueryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:diagnose-db {connection} {--query=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a raw diagnostic query against a specified database connection.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $connectionName = $this->argument('connection');
        $query = $this->option('query');

        if (empty($query)) {
            $this->error('Please provide a query using --query="..."');
            return Command::FAILURE;
        }

        $this->info("Running diagnostic query on connection [{$connectionName}]...");
        $this->line('Query: ' . $query);

        try {
            $results = DB::connection($connectionName)->select($query);
            
            $this->info('Query executed successfully. Results:');
            
            // The result of EXPLAIN ANALYZE is an array of objects, each with a 'QUERY PLAN' property.
            foreach ($results as $row) {
                // Convert row to array to access its properties
                $rowArray = (array)$row;
                foreach ($rowArray as $key => $value) {
                    $this->line($value);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            Log::error("Diagnostic query failed for connection {$connectionName}: " . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncOverdueARCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-overdue-ar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize overdue accounts receivable data from Adempiere to local table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');

        $this->info('Starting overdue accounts receivable synchronization...');
        Log::info('SyncOverdueARCommand: Starting synchronization.');

        $calculationDate = Carbon::today()->toDateString();
        $totalProcessedCount = 0;

        try {
            $connections = config('database.sync_connections.overdue_ar', ['pgsql_surabaya', 'pgsql_bandung', 'pgsql_jakarta']); // Example: make connections configurable

            foreach ($connections as $connectionName) {
                $processedCount = $this->processConnection($connectionName, $calculationDate);
                $totalProcessedCount += $processedCount;
            }

            $this->info("Overdue accounts receivable synchronization completed. Total records processed: {$totalProcessedCount}.");
            Log::info("SyncOverdueARCommand: Synchronization completed successfully. Total records processed: {$totalProcessedCount}.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error during overdue AR synchronization', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function processConnection(string $connectionName, string $calculationDate): int
    {
        $this->info("Processing connection: {$connectionName} for date {$calculationDate}");
        Log::info("SyncOverdueARCommand: Processing connection {$connectionName} for date {$calculationDate}");

        $sql = "
            SELECT 
                org.name AS branch_name,
                SUM(CASE 
                    WHEN ('{$calculationDate}'::date - inv.dateinvoiced::date) BETWEEN 1 AND 30 THEN inv.grandtotal 
                    ELSE 0 
                END) AS days_1_30,
                SUM(CASE 
                    WHEN ('{$calculationDate}'::date - inv.dateinvoiced::date) BETWEEN 31 AND 60 THEN inv.grandtotal 
                    ELSE 0 
                END) AS days_31_60,
                SUM(CASE 
                    WHEN ('{$calculationDate}'::date - inv.dateinvoiced::date) BETWEEN 61 AND 90 THEN inv.grandtotal 
                    ELSE 0 
                END) AS days_61_90,
                SUM(CASE 
                    WHEN ('{$calculationDate}'::date - inv.dateinvoiced::date) > 90 THEN inv.grandtotal 
                    ELSE 0 
                END) AS days_over_90
            FROM c_invoice inv
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            WHERE inv.ad_client_id = 1000001 -- Client ID
                AND inv.issotrx = 'Y' -- Sales transaction
                AND inv.ispaid = 'N' -- Not paid
                AND inv.docstatus IN ('CO', 'CL') -- Completed or Closed
                AND inv.grandtotal > 0
            GROUP BY org.name;
        ";

        $overdueData = DB::connection($connectionName)->select($sql);

        if (empty($overdueData)) {
            $this->info("No overdue AR data found for {$connectionName} on {$calculationDate}.");
            Log::info("SyncOverdueARCommand: No overdue AR data found for {$connectionName} on {$calculationDate}.");
            return 0;
        }

        $processedCount = 0;
        DB::transaction(function () use ($overdueData, $calculationDate, &$processedCount) {
            foreach ($overdueData as $data) {
                DB::table('overdue_accounts_receivables')->updateOrInsert(
                    [
                        'branch_name' => $data->branch_name,
                        'calculation_date' => $calculationDate,
                    ],
                    [
                        'days_1_30_overdue_amount' => $data->days_1_30,
                        'days_31_60_overdue_amount' => $data->days_31_60,
                        'days_61_90_overdue_amount' => $data->days_61_90,
                        'days_over_90_overdue_amount' => $data->days_over_90,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $processedCount++;
            }
        });

        Log::info("SyncOverdueARCommand: Processed {$processedCount} records for branch(es) in {$connectionName}, date: {$calculationDate}");
        return $processedCount;
    }
}

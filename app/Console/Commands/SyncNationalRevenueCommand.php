<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNationalRevenueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-national-revenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Incrementally synchronize national revenue data from Adempiere to the local national_revenues table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');

        $this->info('Starting incremental national revenue synchronization...');
        Log::info('SyncNationalRevenueCommand: Starting incremental synchronization.');

        $timestampFile = storage_path('app/sync_national_revenue_timestamp.txt');
        $syncStartTime = now();

        try {
            // Get the last sync timestamp, or a very old date if it's the first run.
            $lastSyncTimestamp = file_exists($timestampFile) ? file_get_contents($timestampFile) : '1970-01-01 00:00:00';
            $this->info("Checking for updates since: {$lastSyncTimestamp}");
            Log::info("SyncNationalRevenueCommand: Checking for updates since {$lastSyncTimestamp}.");

            $connections = ['pgsql_surabaya', 'pgsql_bandung', 'pgsql_jakarta'];
            $allRevenueDataToUpdate = [];

            foreach ($connections as $connection) {
                $this->info("Finding affected days for connection: {$connection}");
                Log::info("SyncNationalRevenueCommand: Finding affected days for {$connection}.");

                $affectedPairsQuery = "
                    SELECT DISTINCT
                        org.name AS branch_name,
                        CAST(inv.dateinvoiced AS DATE) AS invoice_date
                    FROM c_invoice inv
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                    WHERE
                        inv.updated >= ?
                        AND inv.ad_client_id = 1000001
                        AND inv.issotrx = 'Y'
                        AND inv.docstatus IN ('CO', 'CL')
                        AND inv.isactive = 'Y'
                ";
                $affectedPairs = DB::connection($connection)->select($affectedPairsQuery, [$lastSyncTimestamp]);

                if (empty($affectedPairs)) {
                    $this->info("No updates found for {$connection}.");
                    Log::info("SyncNationalRevenueCommand: No updates found for {$connection}.");
                    continue;
                }

                $this->info(count($affectedPairs) . " affected day(s) found for {$connection}. Recalculating totals...");
                Log::info(count($affectedPairs) . " affected day(s) found for {$connection}.");

                $whereClauses = [];
                $bindings = [];
                foreach ($affectedPairs as $pair) {
                    $whereClauses[] = "(org.name = ? AND CAST(inv.dateinvoiced AS DATE) = ?)";
                    $bindings[] = $pair->branch_name;
                    $bindings[] = $pair->invoice_date;
                }
                $dynamicWhere = implode(' OR ', $whereClauses);

                $recalcQuery = "
                    SELECT
                        org.name AS branch_name,
                        CAST(inv.dateinvoiced AS DATE) AS invoice_date,
                        SUM(invl.linenetamt) AS total_revenue
                    FROM c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                    WHERE
                        ({$dynamicWhere})
                        AND inv.ad_client_id = 1000001
                        AND inv.issotrx = 'Y'
                        AND invl.qtyinvoiced > 0
                        AND invl.linenetamt > 0
                        AND inv.docstatus IN ('CO', 'CL')
                        AND inv.isactive = 'Y'
                    GROUP BY
                        org.name,
                        CAST(inv.dateinvoiced AS DATE)
                ";

                $recalculatedData = DB::connection($connection)->select($recalcQuery, $bindings);
                $allRevenueDataToUpdate = array_merge($allRevenueDataToUpdate, $recalculatedData);
            }

            $recordCount = count($allRevenueDataToUpdate);
            if ($recordCount > 0) {
                $this->info("Found a total of {$recordCount} daily records to update. Processing...");
                Log::info("SyncNationalRevenueCommand: Found a total of {$recordCount} records to update.");

                $processedCount = 0;
                foreach ($allRevenueDataToUpdate as $data) {
                    DB::table('national_revenues')->updateOrInsert(
                        ['branch_name' => $data->branch_name, 'invoice_date' => $data->invoice_date],
                        ['total_revenue' => $data->total_revenue, 'updated_at' => now()]
                    );
                    $processedCount++;
                }
                Log::info("SyncNationalRevenueCommand: Processed {$processedCount} records.");
            } else {
                $this->info('No new updates across all databases. Synchronization is up-to-date.');
                Log::info('SyncNationalRevenueCommand: No new updates found.');
            }

            file_put_contents($timestampFile, $syncStartTime->toDateTimeString());

            $this->info('Incremental national revenue synchronization completed successfully.');
            Log::info('SyncNationalRevenueCommand: Synchronization completed successfully.');
            return 0;

        } catch (\Exception $e) {
            Log::error('Error during incremental national revenue synchronization: ' . $e->getMessage());
            $this->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
    }
}

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
            $lastSyncTimestamp = $this->getLastSyncTimestamp($timestampFile);
            $this->info("Checking for updates since: {$lastSyncTimestamp}");
            Log::info("SyncNationalRevenueCommand: Checking for updates since {$lastSyncTimestamp}.");

            $connections = config('database.sync_connections.national_revenue', ['pgsql_surabaya', 'pgsql_bandung', 'pgsql_jakarta']); // Example: make connections configurable
            $allRevenueDataToUpdate = [];

            foreach ($connections as $connectionName) {
                $this->info("Processing connection: {$connectionName}");
                Log::info("SyncNationalRevenueCommand: Processing connection {$connectionName}.");
                $recalculatedData = $this->fetchUpdatedDataFromSource($connectionName, $lastSyncTimestamp);
                $allRevenueDataToUpdate = array_merge($allRevenueDataToUpdate, $recalculatedData);
            }

            $processedCount = $this->saveRevenueData($allRevenueDataToUpdate);

            if ($processedCount > 0) {
                $this->info("Processed {$processedCount} daily records.");
                Log::info("SyncNationalRevenueCommand: Processed {$processedCount} records.");
            } else {
                $this->info('No new updates across all databases. Synchronization is up-to-date.');
                Log::info('SyncNationalRevenueCommand: No new updates found.');
            }

            $this->recordSyncTimestamp($timestampFile, $syncStartTime);

            $this->info('Incremental national revenue synchronization completed successfully.');
            Log::info('SyncNationalRevenueCommand: Synchronization completed successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Error during incremental national revenue synchronization: ' . $e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getLastSyncTimestamp(string $timestampFile): string
    {
        return file_exists($timestampFile) ? file_get_contents($timestampFile) : '1970-01-01 00:00:00';
    }

    private function fetchUpdatedDataFromSource(string $connectionName, string $lastSyncTimestamp): array
    {
        $this->info("Finding affected day-branch pairs for {$connectionName} since {$lastSyncTimestamp}");
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
        $affectedPairs = DB::connection($connectionName)->select($affectedPairsQuery, [$lastSyncTimestamp]);

        if (empty($affectedPairs)) {
            $this->info("No updates found for {$connectionName}.");
            Log::info("SyncNationalRevenueCommand: No updates found for {$connectionName}.");
            return [];
        }

        $this->info(count($affectedPairs) . " affected day-branch pair(s) found for {$connectionName}. Recalculating totals...");
        Log::info(count($affectedPairs) . " affected day-branch pair(s) found for {$connectionName}.");

        list($dynamicWhere, $bindings) = $this->buildDynamicWhereClauseForPairs($affectedPairs);

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
        return DB::connection($connectionName)->select($recalcQuery, $bindings);
    }

    private function buildDynamicWhereClauseForPairs(array $affectedPairs): array
    {
        $whereClauses = [];
        $bindings = [];
        foreach ($affectedPairs as $pair) {
            $whereClauses[] = "(org.name = ? AND CAST(inv.dateinvoiced AS DATE) = ?)";
            $bindings[] = $pair->branch_name;
            $bindings[] = $pair->invoice_date;
        }
        return [implode(' OR ', $whereClauses), $bindings];
    }

    private function saveRevenueData(array $allRevenueDataToUpdate): int
    {
        $recordCount = count($allRevenueDataToUpdate);
        if ($recordCount === 0) {
            return 0;
        }

        $this->info("Found a total of {$recordCount} daily records to update/insert. Processing...");
        Log::info("SyncNationalRevenueCommand: Found a total of {$recordCount} records to update/insert.");

        $processedCount = 0;
        DB::transaction(function () use ($allRevenueDataToUpdate, &$processedCount) {
            foreach ($allRevenueDataToUpdate as $data) {
                DB::table('national_revenues')->updateOrInsert(
                    ['branch_name' => $data->branch_name, 'invoice_date' => $data->invoice_date],
                    ['total_revenue' => $data->total_revenue, 'updated_at' => now()]
                );
                $processedCount++;
            }
        });
        return $processedCount;
    }

    private function recordSyncTimestamp(string $timestampFile, \Carbon\Carbon $syncStartTime): void
    {
        file_put_contents($timestampFile, $syncStartTime->toDateTimeString());
    }
}

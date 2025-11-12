<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalesMetricsController extends Controller
{
    public function getData(Request $request)
    {
        // Increase execution time limit for querying multiple databases
        set_time_limit(180); // 3 minutes
        ini_set('max_execution_time', 180);

        try {
            $location = $request->input('location', 'National');
            // Use yesterday (H-1) since dashboard is updated daily at night
            $yesterday = Carbon::now()->subDay();
            $startDate = Carbon::parse($request->input('start_date', $yesterday->copy()->subDays(21)))->format('Y-m-d');
            $endDate = Carbon::parse($request->input('end_date', $yesterday))->format('Y-m-d');

            // Get current date from request or default to today (matching AccountsReceivableController pattern)
            $currentDate = $request->input('ar_current_date', now()->toDateString());

            // Check if only AR data is requested (when AR date changes)
            $arOnly = filter_var($request->input('ar_only', false), FILTER_VALIDATE_BOOLEAN);

            $locationFilter = ($location === 'National') ? '%' : $location;

            // Initialize response data
            $responseData = [];

            // Only query Sales Order, Stock Value, and Store Returns if not ar_only
            if (!$arOnly) {
                // 1. Sales Order Query
                $salesOrderQuery = "
            SELECT
              SUM(d.linenetamt) AS total_so,
              SUM(d.qtydelivered * d.priceactual) AS total_completed_so,
              SUM((d.qtyordered - d.qtydelivered) * d.priceactual) AS total_pending_so
            FROM
              c_orderline d
              INNER JOIN c_order h ON d.c_order_id = h.c_order_id
              INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE
              h.ad_client_id = 1000001
              AND h.issotrx = 'Y'
              AND h.docstatus = 'CO'
              AND d.linenetamt > 0
              AND h.isactive = 'Y'
              AND DATE(h.dateordered) BETWEEN ? AND ?
            ";
                $soBindings = [$startDate, $endDate];
                if ($location !== 'National') {
                    $salesOrderQuery .= " AND org.name LIKE ?";
                    $soBindings[] = $locationFilter;
                }
                $salesOrderData = DB::selectOne($salesOrderQuery, $soBindings);

                // 2. Stock Value Query - Current stock value (point-in-time calculation)
                $stockValueQuery = "
            SELECT
              SUM(s.qtyonhand * prc.pricelist * 0.615) as stock_value
            FROM
              m_storage s
              INNER JOIN m_product prd on s.m_product_id = prd.m_product_id
              INNER JOIN m_productprice prc on prd.m_product_id = prc.m_product_id
              INNER JOIN m_pricelist_version plv ON prc.m_pricelist_version_id = plv.m_pricelist_version_id
              INNER JOIN m_locator loc ON s.m_locator_id = loc.m_locator_id
              INNER JOIN ad_org org ON s.ad_org_id = org.ad_org_id
            WHERE
              UPPER(plv.name) LIKE '%PURCHASE%'
              AND plv.isactive = 'Y'
              AND s.qtyonhand > 0
            ";

                $stockValueBindings = [];
                if ($location !== 'National') {
                    $stockValueQuery .= " AND org.name LIKE ?";
                    $stockValueBindings[] = $locationFilter;
                }

                $stockValueData = DB::selectOne($stockValueQuery, $stockValueBindings);

                // 3. Store Returns Query
                $storeReturnsQuery = "
            SELECT
              SUM(d.linenetamt) AS store_returns
            FROM
              c_invoiceline d
              INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
              INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE
              h.issotrx = 'Y'
              AND h.ad_client_id = 1000001
              AND d.qtyinvoiced > 0
              AND d.linenetamt > 0
              AND h.docstatus in ('CO', 'CL')
              AND h.documentno LIKE 'CNC%'
              AND DATE(h.dateinvoiced) BETWEEN ? AND ?
            ";
                $srBindings = [$startDate, $endDate];
                if ($location !== 'National') {
                    $storeReturnsQuery .= " AND org.name LIKE ?";
                    $srBindings[] = $locationFilter;
                }
                $storeReturnsData = DB::selectOne($storeReturnsQuery, $srBindings);

                $dateRange = ChartHelper::formatDateRange($startDate, $endDate);

                $responseData = [
                    'total_so' => (float)($salesOrderData->total_so ?? 0),
                    'completed_so' => (float)($salesOrderData->total_completed_so ?? 0),
                    'pending_so' => (float)($salesOrderData->total_pending_so ?? 0),
                    'stock_value' => (float)($stockValueData->stock_value ?? 0),
                    'store_returns' => (float)($storeReturnsData->store_returns ?? 0),
                    'date_range' => $dateRange,
                ];
            }

            // 4. Accounts Receivable Pie Chart Query (matching AccountsReceivableController with 'all' filter)
            // Query multiple branch databases in real-time like AccountsReceivableController

            // Define all branch database connections in desired display order
            $branchConnections = [
                'pgsql_trg',  // 1. Tangerang (TGR)
                'pgsql_bks',  // 2. Bekasi (BKS)
                'pgsql_jkt',  // 3. Jakarta (JKT)
                'pgsql_ptk',  // 4. Pontianak (PTK)
                'pgsql_lmp',  // 5. Lampung (LMP)
                'pgsql_bjm',  // 6. Banjarmasin (BJM)
                'pgsql_crb',  // 7. Cirebon (CRB)
                'pgsql_bdg',  // 8. Bandung (BDG)
                'pgsql_mks',  // 9. Makassar (MKS) - Offline/Anomali
                'pgsql_sby',  // 10. Surabaya (SBY) - Offline/Anomali
                'pgsql_smg',  // 11. Semarang (SMG)
                'pgsql_pwt',  // 12. Purwokerto (PWT)
                'pgsql_dps',  // 13. Denpasar (DPS)
                'pgsql_plb',  // 14. Palembang (PLB)
                'pgsql_pdg',  // 15. Padang (PDG)
                'pgsql_mdn',  // 16. Medan (MDN)
                'pgsql_pku',  // 17. Pekanbaru (PKU)
            ];

            // Define branches that are known to be offline or have anomalies
            $offlineBranches = ['pgsql_mks', 'pgsql_sby'];

            // Filter branch connections based on location
            $connectionsToQuery = [];
            if ($location === 'National' || $location === '%') {
                // Query all branches
                $connectionsToQuery = $branchConnections;
            } else {
                // Query only the selected branch
                $branchConnection = ChartHelper::getBranchConnection($location);
                if ($branchConnection) {
                    $connectionsToQuery = [$branchConnection];
                } else {
                    // If branch connection not found, return empty data
                    $connectionsToQuery = [];
                }
            }

            // Build SQL query with 'all' filter (same as AccountsReceivableController lines 47-98)
            $sql = "
            SELECT
                branch_name,
                SUM(CASE WHEN age >= 0 AND age <= 104 AND (totallines - (bayar * pengali)) <> 0
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_0_104,
                SUM(CASE WHEN age >= 105 AND age <= 120 AND (totallines - (bayar * pengali)) <> 0
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_105_120,
                SUM(CASE WHEN age > 120 AND (totallines - (bayar * pengali)) <> 0
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_120_plus,
                SUM(CASE WHEN age >= 0 AND (totallines - (bayar * pengali)) <> 0
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
            FROM (
                SELECT
                    inv.totallines,
                    org.name as branch_name,
                    (
                        SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt), 0)
                        FROM c_allocationline alocln
                        INNER JOIN c_allocationhdr alochdr ON alocln.c_allocationhdr_id = alochdr.c_allocationhdr_id
                        WHERE alocln.c_invoice_id = inv.c_invoice_id
                            AND alochdr.docstatus IN ('CO', 'IN')
                            AND alochdr.ad_client_id = 1000001
                            AND alochdr.datetrx <= ?
                    ) as bayar,
                    CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NDC') THEN 1
                        ELSE -1
                    END as pengali,
                    (CURRENT_DATE - inv.dateinvoiced::date) as age
                FROM c_invoice inv
                INNER JOIN c_bpartner bp ON inv.c_bpartner_id = bp.c_bpartner_id
                INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                WHERE SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NCC', 'CNC', 'NDC')
                    AND inv.isactive = 'Y'
                    AND inv.ad_client_id = 1000001
                    AND bp.isactive = 'Y'
                    AND inv.issotrx = 'Y'
                    AND inv.docstatus IN ('CO', 'CL')
                    AND bp.iscustomer = 'Y'
                    AND inv.dateinvoiced <= ?
                    AND inv.c_bpartner_id IS NOT NULL
                    AND inv.totallines IS NOT NULL
            ) as source
            WHERE (totallines - (bayar * pengali)) <> 0
                AND age >= 0
            GROUP BY branch_name
            ORDER BY total_overdue DESC
            ";

            // Query all branch databases and collect results
            $allResults = collect();
            $failedBranches = [];

            foreach ($connectionsToQuery as $connection) {
                // Skip offline branches
                if (in_array($connection, $offlineBranches)) {
                    $failedBranches[] = $connection;
                    continue;
                }

                // Perform lightweight socket check before attempting database query
                if (!$this->canConnectToBranch($connection)) {
                    Log::warning("Sales Metrics AR - Skipping {$connection} due to connectivity check failure");
                    $failedBranches[] = $connection;
                    continue;
                }

                try {
                    // Set shorter timeout per connection (30 seconds)
                    DB::connection($connection)->statement("SET statement_timeout = 30000"); // 30 seconds

                    $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate]);
                    $allResults = $allResults->merge($branchResults);

                    // Reset timeout
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $e) {
                    // Log error and track failed branch
                    Log::warning("Sales Metrics AR - Failed to query {$connection}: " . $e->getMessage());
                    $failedBranches[] = $connection;

                    // Try to reset timeout even on error
                    try {
                        DB::connection($connection)->statement("SET statement_timeout = 0");
                    } catch (\Exception $resetError) {
                        // Ignore reset errors
                    }

                    // Continue to next branch without breaking
                    continue;
                }
            }

            // Log summary of failed branches if any
            if (!empty($failedBranches)) {
                Log::info("Sales Metrics AR - Failed branches: " . implode(', ', $failedBranches));
            }

            // Map results and ensure no negative values
            $queryResult = $allResults->map(function ($item) {
                $item->range_0_104 = max(0, (float) ($item->range_0_104 ?? 0));
                $item->range_105_120 = max(0, (float) ($item->range_105_120 ?? 0));
                $item->range_120_plus = max(0, (float) ($item->range_120_plus ?? 0));
                $item->total_overdue = max(0, (float) $item->total_overdue);
                return $item;
            });

            // Aggregate results for pie chart (sum across all branches)
            $arPieData = (object) [
                'range_0_104' => $queryResult->sum('range_0_104'),
                'range_105_120' => $queryResult->sum('range_105_120'),
                'range_120_plus' => $queryResult->sum('range_120_plus'),
            ];

            $arPieChartData = [
                'labels' => ['0 - 104 Days', '105 - 120 Days', '> 120 Days'],
                'data' => [
                    (float)($arPieData->range_0_104 ?? 0),
                    (float)($arPieData->range_105_120 ?? 0),
                    (float)($arPieData->range_120_plus ?? 0),
                ],
                'total' => array_sum([
                    (float)($arPieData->range_0_104 ?? 0),
                    (float)($arPieData->range_105_120 ?? 0),
                    (float)($arPieData->range_120_plus ?? 0),
                ]),
                'current_date' => $currentDate
            ];

            // Add AR data to response
            $responseData['ar_pie_chart'] = $arPieChartData;

            return response()->json($responseData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Perform a quick socket connectivity check to avoid long Postgres connection timeouts.
     */
    protected function canConnectToBranch(string $connection, int $timeoutSeconds = 3): bool
    {
        $config = config("database.connections.{$connection}");

        if (!is_array($config)) {
            return false;
        }

        $host = $config['host'] ?? null;
        $port = (int)($config['port'] ?? 5432);

        if (empty($host) || $port <= 0) {
            return false;
        }

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errorNumber,
            $errorString,
            $timeoutSeconds
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }

    public function getLocations()
    {
        try {
            $locations = ChartHelper::getLocations();

            // Add National option at the beginning
            $locationOptions = collect([
                [
                    'value' => '%',
                    'display' => 'National'
                ]
            ]);

            // Map locations to include both value (full name) and display name
            $branchOptions = $locations->map(function ($location) {
                return [
                    'value' => $location,
                    'display' => ChartHelper::getBranchDisplayName($location)
                ];
            });

            // Merge National option with branch options
            $allOptions = $locationOptions->merge($branchOptions);

            return response()->json($allOptions);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

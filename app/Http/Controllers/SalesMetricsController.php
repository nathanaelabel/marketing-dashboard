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
        set_time_limit(180);
        ini_set('max_execution_time', 180);

        try {
            $location = $request->input('location', 'National');
            // Gunakan H-1 karena dashboard diupdate setiap malam
            $yesterday = Carbon::now()->subDay();
            $startDate = Carbon::parse($request->input('start_date', $yesterday->copy()->subDays(21)))->format('Y-m-d');
            $endDate = Carbon::parse($request->input('end_date', $yesterday))->format('Y-m-d');

            // Ambil tanggal dari request atau default ke hari ini
            $currentDate = $request->input('ar_current_date', now()->toDateString());

            // Cek apakah hanya data AR yang diminta
            $arOnly = filter_var($request->input('ar_only', false), FILTER_VALIDATE_BOOLEAN);

            $locationFilter = ($location === 'National') ? '%' : $location;

            $responseData = [];

            // Hanya query SO, Stok, dan Retur jika bukan ar_only
            if (!$arOnly) {
                // Query Sales Order
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

                // Query Nilai Stok
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

                // Query Retur Toko
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

            // Query Piutang Usaha untuk Pie Chart

            // Daftar koneksi database cabang
            $branchConnections = [
                'pgsql_trg',
                'pgsql_bks',
                'pgsql_jkt',
                'pgsql_ptk',
                'pgsql_lmp',
                'pgsql_bjm',
                'pgsql_crb',
                'pgsql_bdg',
                'pgsql_mks',
                'pgsql_sby',
                'pgsql_smg',
                'pgsql_pwt',
                'pgsql_dps',
                'pgsql_plb',
                'pgsql_pdg',
                'pgsql_mdn',
                'pgsql_pku',
            ];

            // Cabang yang diketahui offline atau memiliki anomali
            $offlineBranches = ['pgsql_mks', 'pgsql_sby'];

            // Filter koneksi cabang berdasarkan lokasi
            $connectionsToQuery = [];
            if ($location === 'National' || $location === '%') {
                $connectionsToQuery = $branchConnections;
            } else {
                $branchConnection = ChartHelper::getBranchConnection($location);
                if ($branchConnection) {
                    $connectionsToQuery = [$branchConnection];
                } else {
                    $connectionsToQuery = [];
                }
            }

            // Query SQL dengan filter 'all'
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
                    (? ::date - inv.dateinvoiced::date) as age
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

            // Query semua database cabang
            $allResults = collect();
            $failedBranches = [];

            foreach ($connectionsToQuery as $connection) {
                // Lewati cabang offline
                if (in_array($connection, $offlineBranches)) {
                    $failedBranches[] = $connection;
                    continue;
                }

                // Cek konektivitas socket sebelum query database
                if (!$this->canConnectToBranch($connection)) {
                    Log::warning("Sales Metrics AR - Skipping {$connection} due to connectivity check failure");
                    $failedBranches[] = $connection;
                    continue;
                }

                try {
                    // Timeout per koneksi 30 detik
                    DB::connection($connection)->statement("SET statement_timeout = 30000"); // 30 seconds

                    $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate, $currentDate]);
                    $allResults = $allResults->merge($branchResults);

                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $e) {
                    // Log error dan catat cabang yang gagal
                    Log::warning("Sales Metrics AR - Failed to query {$connection}: " . $e->getMessage());
                    $failedBranches[] = $connection;

                    try {
                        DB::connection($connection)->statement("SET statement_timeout = 0");
                    } catch (\Exception $resetError) {
                    }

                    continue;
                }
            }

            if (!empty($failedBranches)) {
                Log::info("Sales Metrics AR - Failed branches: " . implode(', ', $failedBranches));
            }

            // Map hasil dan pastikan tidak ada nilai negatif
            $queryResult = $allResults->map(function ($item) {
                $item->range_0_104 = max(0, (float) ($item->range_0_104 ?? 0));
                $item->range_105_120 = max(0, (float) ($item->range_105_120 ?? 0));
                $item->range_120_plus = max(0, (float) ($item->range_120_plus ?? 0));
                $item->total_overdue = max(0, (float) $item->total_overdue);
                return $item;
            });

            // Agregasi hasil untuk pie chart
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

            $responseData['ar_pie_chart'] = $arPieChartData;

            return response()->json($responseData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Cek konektivitas socket untuk menghindari timeout koneksi database
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

            $locationOptions = collect([
                [
                    'value' => '%',
                    'display' => 'National'
                ]
            ]);

            $branchOptions = $locations->map(function ($location) {
                return [
                    'value' => $location,
                    'display' => ChartHelper::getBranchDisplayName($location)
                ];
            });

            $allOptions = $locationOptions->merge($branchOptions);

            return response()->json($allOptions);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

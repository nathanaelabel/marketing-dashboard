<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Helpers\TableHelper;
use App\Helpers\ChartHelper;

class SalesComparisonController extends Controller
{
    private function getBranchConfig()
    {
        // Hard-coded branch configuration
        return [
            'PWM Surabaya' => [
                'code' => 'SBY',
                'locator_id' => 1000011,
                'locator_value' => 'Gudang PWM SBY',
                'pricelist_version_id' => 110000004,
                'pricelist_name' => '20061101SBY-Purchase01',
            ],
            'PWM Jakarta' => [
                'code' => 'JKT',
                'locator_id' => 1000006,
                'locator_value' => 'Gudang PWM JKT',
                'pricelist_version_id' => 10000001,
                'pricelist_name' => '20060629JKT-Purchase',
            ],
            'PWM Bandung' => [
                'code' => 'BDG',
                'locator_id' => 1000003,
                'locator_value' => 'Gudang PWM BDG',
                'pricelist_version_id' => 20000006,
                'pricelist_name' => '20061101BDG-Purchase01',
            ],
            'PWM Semarang' => [
                'code' => 'SMG',
                'locator_id' => 1000012,
                'locator_value' => 'Gudang PWM SMG',
                'pricelist_version_id' => 30000007,
                'pricelist_name' => '20061101SMG-Purchase01',
            ],
            'PWM Medan' => [
                'code' => 'MDN',
                'locator_id' => 1000007,
                'locator_value' => 'Gudang PWM MDN',
                'pricelist_version_id' => 40000000,
                'pricelist_name' => '20060628MDN-Purchase',
            ],
            'PWM Palembang' => [
                'code' => 'PLB',
                'locator_id' => 1000009,
                'locator_value' => 'Gudang PWM PLB',
                'pricelist_version_id' => 50000004,
                'pricelist_name' => '20060628PLB-Purchase',
            ],
            'PWM Banjarmasin' => [
                'code' => 'BJM',
                'locator_id' => 1000004,
                'locator_value' => 'Gudang PWM BJM',
                'pricelist_version_id' => 60000004,
                'pricelist_name' => '20060628BJM-Purchase',
            ],
            'PWM Denpasar' => [
                'code' => 'DPS',
                'locator_id' => 1000005,
                'locator_value' => 'Gudang PWM DPS',
                'pricelist_version_id' => 70000000,
                'pricelist_name' => '20060422DPS-Purchase',
            ],
            'PWM Makassar' => [
                'code' => 'MKS',
                'locator_id' => 1000002,
                'locator_value' => 'Gudang PM',
                'pricelist_version_id' => 80000007,
                'pricelist_name' => '20061101MKS-Purchase01',
            ],
            'PWM Pekanbaru' => [
                'code' => 'PKU',
                'locator_id' => 1000008,
                'locator_value' => 'Gudang PWM PKU',
                'pricelist_version_id' => 90000000,
                'pricelist_name' => '20070301PKU-Purchase',
            ],
            'PWM Pontianak' => [
                'code' => 'PTK',
                'locator_id' => 1000010,
                'locator_value' => 'Gudang PWM PTK',
                'pricelist_version_id' => 120000004,
                'pricelist_name' => '20061101PTK-Purchase01',
            ],
            'PWM Cirebon' => [
                'code' => 'CRB',
                'locator_id' => 1000046,
                'locator_value' => 'Gudang PWM CRB',
                'pricelist_version_id' => 130000000,
                'pricelist_name' => '20110316 CRB-Purchase',
            ],
            'PWM Padang' => [
                'code' => 'PDG',
                'locator_id' => 1000051,
                'locator_value' => 'Gudang PWM PDG',
                'pricelist_version_id' => 140000001,
                'pricelist_name' => '20110316 PDG-Purchase',
            ],
            'PWM Purwokerto' => [
                'code' => 'PWT',
                'locator_id' => 1000055,
                'locator_value' => 'Gudang PWM PWT',
                'pricelist_version_id' => 150000001,
                'pricelist_name' => '20110316 PWT-Purchase',
            ],
            'PWM Bekasi' => [
                'code' => 'BKS',
                'locator_id' => 1000059,
                'locator_value' => 'Gudang PM ABG',
                'pricelist_version_id' => 160000001,
                'pricelist_name' => 'BKS-Purchase',
            ],
            'MPM Tangerang' => [
                'code' => 'TGR',
                'locator_id' => 1000065,
                'locator_value' => 'Gudang PWM MPM',
                'pricelist_version_id' => 170000000,
                'pricelist_name' => 'MPM-Purchase',
            ],
            'PWM Lampung' => [
                'code' => 'LMP',
                'locator_id' => 1000069,
                'locator_value' => 'Gudang PWM LMP',
                'pricelist_version_id' => 180000000,
                'pricelist_name' => 'Lampung-Purchase',
            ],
        ];
    }

    public function index()
    {
        return view('sales-comparison');
    }

    public function getData(Request $request)
    {
        try {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $salesDate = $yesterday;
            $stokBdpDate = $today;

            // Tingkatkan batas waktu eksekusi untuk query berat
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $branchMapping = TableHelper::getBranchMapping();
            $branchConfig = $this->getBranchConfig();
            $allBranchData = $this->getAllBranchesData($salesDate, $stokBdpDate, $branchConfig);

            $data = [];
            $no = 1;

            $totals = [
                'sales_mika' => 0,
                'sales_sparepart' => 0,
                'total_sales' => 0,
                'stok_mika' => 0,
                'stok_sparepart' => 0,
                'total_stok' => 0,
                'bdp_mika' => 0,
                'bdp_sparepart' => 0,
                'total_bdp' => 0,
                'stok_bdp_mika' => 0,
                'stok_bdp_sparepart' => 0,
                'total_stok_bdp' => 0,
                'total_mika' => 0,
                'total_sparepart' => 0,
                'grand_total' => 0,
            ];

            foreach ($branchMapping as $branchName => $branchCode) {
                if (!isset($branchConfig[$branchName])) {
                    continue;
                }

                $branchData = $allBranchData[$branchName] ?? [
                    'sales_mika' => 0,
                    'sales_sparepart' => 0,
                    'stok_mika' => 0,
                    'stok_sparepart' => 0,
                    'bdp_mika' => 0,
                    'bdp_sparepart' => 0,
                    'connection_failed' => false,
                ];

                // Lewati cabang dengan masalah koneksi
                if (isset($branchData['connection_failed']) && $branchData['connection_failed']) {
                    $data[] = [
                        'no' => $no++,
                        'branch_code' => $branchCode,
                        'branch_name' => $branchName,
                        'connection_failed' => true,
                        'error_message' => 'Koneksi gagal. Silakan coba lagi.',
                        'sales_mika' => null,
                        'sales_sparepart' => null,
                        'total_sales' => null,
                        'stok_mika' => null,
                        'stok_sparepart' => null,
                        'total_stok' => null,
                        'bdp_mika' => null,
                        'bdp_sparepart' => null,
                        'total_bdp' => null,
                        'stok_bdp_mika' => null,
                        'stok_bdp_sparepart' => null,
                        'total_stok_bdp' => null,
                        'total_mika' => null,
                        'total_sparepart' => null,
                        'grand_total' => null,
                    ];
                    continue;
                }

                $totalSales = $branchData['sales_mika'] + $branchData['sales_sparepart'];
                $totalStok = $branchData['stok_mika'] + $branchData['stok_sparepart'];
                $totalBdp = $branchData['bdp_mika'] + $branchData['bdp_sparepart'];
                $stokBdpMika = $branchData['stok_mika'] + $branchData['bdp_mika'];
                $stokBdpSparepart = $branchData['stok_sparepart'] + $branchData['bdp_sparepart'];
                $totalStokBdp = $totalStok + $totalBdp;
                $totalMika = $branchData['sales_mika'] + $branchData['stok_mika'] + $branchData['bdp_mika'];
                $totalSparepart = $branchData['sales_sparepart'] + $branchData['stok_sparepart'] + $branchData['bdp_sparepart'];
                $grandTotal = $totalSales + $totalStok + $totalBdp;

                $data[] = [
                    'no' => $no++,
                    'branch_code' => $branchCode,
                    'branch_name' => $branchName,
                    'connection_failed' => false,
                    'sales_mika' => $branchData['sales_mika'],
                    'sales_sparepart' => $branchData['sales_sparepart'],
                    'total_sales' => $totalSales,
                    'stok_mika' => $branchData['stok_mika'],
                    'stok_sparepart' => $branchData['stok_sparepart'],
                    'total_stok' => $totalStok,
                    'bdp_mika' => $branchData['bdp_mika'],
                    'bdp_sparepart' => $branchData['bdp_sparepart'],
                    'total_bdp' => $totalBdp,
                    'stok_bdp_mika' => $stokBdpMika,
                    'stok_bdp_sparepart' => $stokBdpSparepart,
                    'total_stok_bdp' => $totalStokBdp,
                    'total_mika' => $totalMika,
                    'total_sparepart' => $totalSparepart,
                    'grand_total' => $grandTotal,
                ];

                $totals['sales_mika'] += $branchData['sales_mika'];
                $totals['sales_sparepart'] += $branchData['sales_sparepart'];
                $totals['total_sales'] += $totalSales;
                $totals['stok_mika'] += $branchData['stok_mika'];
                $totals['stok_sparepart'] += $branchData['stok_sparepart'];
                $totals['total_stok'] += $totalStok;
                $totals['bdp_mika'] += $branchData['bdp_mika'];
                $totals['bdp_sparepart'] += $branchData['bdp_sparepart'];
                $totals['total_bdp'] += $totalBdp;
                $totals['stok_bdp_mika'] += $stokBdpMika;
                $totals['stok_bdp_sparepart'] += $stokBdpSparepart;
                $totals['total_stok_bdp'] += $totalStokBdp;
                $totals['total_mika'] += $totalMika;
                $totals['total_sparepart'] += $totalSparepart;
                $totals['grand_total'] += $grandTotal;
            }

            return response()->json([
                'data' => $data,
                'totals' => $totals,
                'sales_date' => $salesDate,
                'stok_bdp_date' => $stokBdpDate,
                'today' => $today,
                'formatted_date' => date('d F Y', strtotime($today)),
                'formatted_sales_date' => date('d F Y', strtotime($salesDate)),
                'formatted_stok_bdp_date' => date('d F Y', strtotime($stokBdpDate)),
                'total_count' => count($data)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('SalesComparisonController', 'getData', $e, [
                'sales_date' => $yesterday ?? null,
                'stok_bdp_date' => $today ?? null,
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            return response()->json([
                'error' => 'Gagal mengambil data',
                'message' => 'Terjadi kesalahan saat mengambil data. Silakan periksa log untuk detail.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lakukan pengecekan koneksi socket untuk menghindari timeout koneksi Postgres yang lama
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

    /**
     * @param string $branchName Nama cabang (e.g., 'PWM Pontianak', 'MPM Tangerang')
     * @param array $config Konfigurasi cabang (pricelist_version_id, locator_id)
     * @param string $stokBdpDate Tanggal untuk data stok dan BDP
     * @return array Mengembalikan data stok dan BDP atau flag connection_failed
     */
    private function queryBranchRealtimeData($branchName, $config, $stokBdpDate)
    {
        try {
            $connectionName = ChartHelper::getBranchConnection($branchName);

            if (!$connectionName) {
                return ['connection_failed' => true];
            }

            // Lakukan pengecekan koneksi socket sebelum melakukan query database
            if (!$this->canConnectToBranch($connectionName)) {
                Log::warning("Skipping {$branchName} due to connectivity check failure");
                return ['connection_failed' => true];
            }

            // Set timeout koneksi 60 detik
            DB::connection($connectionName)->statement("SET statement_timeout = 60000");

            $pricelistVersionId = $config['pricelist_version_id'];
            $locatorId = $config['locator_id'];

            // Query STOK - Gabungan MIKA dan SPARE PART (tanpa filter org.name karena sudah spesifik via locator_id dan pricelist_version_id)
            $combinedQuery = "
                SELECT
                    -- STOK MIKA
                    COALESCE(SUM(CASE 
                        WHEN (cat.value = 'MIKA' OR (cat.value = 'PRODUCT IMPORT' AND prd.name NOT LIKE '%BOHLAM%' AND psc.value = 'MIKA') OR (cat.value = 'PRODUCT IMPORT' AND (prd.name LIKE '%FILTER UDARA%' OR prd.name LIKE '%SWITCH REM%' OR prd.name LIKE '%DOP RITING%')))
                        THEN stock_qty.qty_onhand * prc.pricelist * 0.615 
                        ELSE 0 
                    END), 0) AS stok_mika,
                    
                    -- STOK SPARE PART
                    COALESCE(SUM(CASE 
                        WHEN cat.name = 'SPARE PART'
                        THEN stock_qty.qty_onhand * prc.pricelist * 0.615 
                        ELSE 0 
                    END), 0) AS stok_sparepart
                FROM m_product prd
                INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                INNER JOIN m_productprice prc ON prd.m_product_id = prc.m_product_id
                INNER JOIN m_pricelist_version plv ON plv.m_pricelist_version_id = prc.m_pricelist_version_id
                LEFT JOIN (
                    SELECT st.m_product_id, SUM(st.qtyonhand) AS qty_onhand
                    FROM m_storage st
                    INNER JOIN m_locator loc ON st.m_locator_id = loc.m_locator_id
                    WHERE loc.m_locator_id = ? AND st.qtyonhand > 0
                    GROUP BY st.m_product_id
                ) stock_qty ON prd.m_product_id = stock_qty.m_product_id
                WHERE (
                        cat.value = 'MIKA'
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND prd.name NOT LIKE '%BOHLAM%'
                            AND psc.value = 'MIKA'
                        )
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND (
                                prd.name LIKE '%FILTER UDARA%'
                                OR prd.name LIKE '%SWITCH REM%'
                                OR prd.name LIKE '%DOP RITING%'
                            )
                        )
                        OR cat.name = 'SPARE PART'
                    )
                    AND plv.m_pricelist_version_id = ?
            ";

            $stockData = DB::connection($connectionName)->selectOne($combinedQuery, [$locatorId, $pricelistVersionId]);
            $stokMika = $stockData ? (float)$stockData->stok_mika : 0;
            $stokSparepart = $stockData ? (float)$stockData->stok_sparepart : 0;

            // Query BDP - Gabungan MIKA dan SPARE PART (tanpa filter org.name karena query ke database cabang spesifik)
            $bdpQuery = "
                SELECT
                    -- BDP MIKA
                    COALESCE(SUM(CASE 
                        WHEN (cat.value = 'MIKA' OR (cat.value = 'PRODUCT IMPORT' AND prd.name NOT LIKE '%BOHLAM%' AND psc.value = 'MIKA') OR (cat.value = 'PRODUCT IMPORT' AND (prd.name LIKE '%FILTER UDARA%' OR prd.name LIKE '%SWITCH REM%' OR prd.name LIKE '%DOP RITING%')))
                        THEN (d.qtyinvoiced - COALESCE(match_qty.qtymr, 0)) * d.priceactual
                        ELSE 0 
                    END), 0) AS bdp_mika,
                    
                    -- BDP SPARE PART
                    COALESCE(SUM(CASE 
                        WHEN cat.name = 'SPARE PART'
                        THEN (d.qtyinvoiced - COALESCE(match_qty.qtymr, 0)) * d.priceactual
                        ELSE 0 
                    END), 0) AS bdp_sparepart
                FROM c_invoice h
                INNER JOIN c_invoiceline d ON d.c_invoice_id = h.c_invoice_id
                LEFT JOIN (
                    SELECT c_invoiceline_id, SUM(qty) as qtymr
                    FROM m_matchinv
                    WHERE created::date <= ?::date
                    GROUP BY c_invoiceline_id
                ) match_qty ON d.c_invoiceline_id = match_qty.c_invoiceline_id
                INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE h.documentno LIKE 'INS-%'
                    AND h.docstatus = 'CO'
                    AND h.issotrx = 'N'
                    AND h.dateinvoiced::date <= ?::date
                    AND d.qtyinvoiced <> COALESCE(match_qty.qtymr, 0)
            ";

            $bdpData = DB::connection($connectionName)->selectOne($bdpQuery, [$stokBdpDate, $stokBdpDate]);
            $bdpMika = $bdpData ? (float)$bdpData->bdp_mika : 0;
            $bdpSparepart = $bdpData ? (float)$bdpData->bdp_sparepart : 0;

            DB::connection($connectionName)->statement("SET statement_timeout = 0");

            return [
                'stok_mika' => $stokMika,
                'stok_sparepart' => $stokSparepart,
                'bdp_mika' => $bdpMika,
                'bdp_sparepart' => $bdpSparepart,
                'connection_failed' => false,
            ];
        } catch (\Exception $e) {
            Log::error("Branch realtime query failed for {$branchName}: " . $e->getMessage());

            try {
                $connectionName = ChartHelper::getBranchConnection($branchName);
                if ($connectionName) {
                    DB::connection($connectionName)->statement("SET statement_timeout = 0");
                }
            } catch (\Exception $resetError) {
            }

            return ['connection_failed' => true];
        }
    }

    /**
     * 
     * @param string
     * @param string
     * @param array
     */
    private function getAllBranchesData($salesDate, $stokBdpDate, $branchConfig)
    {
        $salesDateStart = $salesDate . ' 00:00:00';
        $salesDateEnd = $salesDate . ' 23:59:59';

        $results = [];
        foreach (array_keys($branchConfig) as $branchName) {
            $results[$branchName] = [
                'sales_mika' => 0,
                'sales_sparepart' => 0,
                'stok_mika' => 0,
                'stok_sparepart' => 0,
                'bdp_mika' => 0,
                'bdp_sparepart' => 0,
            ];
        }

        // 1. Get SALES data for all branches at once
        $salesMikaQuery = "
            SELECT
                org.name as branch_name,
                SUM(CASE
                    WHEN SUBSTR(inv.documentno, 1, 3) = 'INC' THEN invl.linenetamt
                    WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN -invl.linenetamt
                    ELSE 0
                END) AS total_sales
            FROM c_invoice inv
            INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            INNER JOIN c_bpartner cust ON inv.c_bpartner_id = cust.c_bpartner_id
            INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
            LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
            WHERE inv.ad_client_id = 1000001
                AND inv.issotrx = 'Y'
                AND invl.qtyinvoiced > 0
                AND invl.linenetamt > 0
                AND inv.docstatus IN ('CO', 'CL')
                AND inv.isactive = 'Y'
                AND inv.documentno LIKE 'INC%'
                AND (
                    cat.value = 'MIKA'
                    OR (
                        cat.value = 'PRODUCT IMPORT' 
                        AND prd.name NOT LIKE '%BOHLAM%'
                        AND psc.value = 'MIKA'
                    )
                    OR (
                        cat.value = 'PRODUCT IMPORT' 
                        AND (
                            prd.name LIKE '%FILTER UDARA%'
                            OR prd.name LIKE '%SWITCH REM%'
                            OR prd.name LIKE '%DOP RITING%'
                        )
                    )
                )
                AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                AND inv.dateinvoiced >= ?
                AND inv.dateinvoiced <= ?
            GROUP BY org.name
        ";

        $salesMikaData = DB::select($salesMikaQuery, [$salesDateStart, $salesDateEnd]);

        // Mapping nama org dari database ke nama branchConfig
        $orgNameMapping = [
            'MPM Tangerang' => 'MPM Tangerang',
            'PM Bekasi' => 'PWM Bekasi',
            'PWM Bekasi' => 'PWM Bekasi',
            'PWM Jakarta' => 'PWM Jakarta',
            'PWM Pontianak' => 'PWM Pontianak',
            'PWM Lampung' => 'PWM Lampung',
            'PWM Banjarmasin' => 'PWM Banjarmasin',
            'PWM Cirebon' => 'PWM Cirebon',
            'PWM Bandung' => 'PWM Bandung',
            'PM Makassar' => 'PWM Makassar',
            'PWM Makassar' => 'PWM Makassar',
            'PWM Surabaya' => 'PWM Surabaya',
            'PWM Semarang' => 'PWM Semarang',
            'PWM Purwokerto' => 'PWM Purwokerto',
            'PWM Denpasar' => 'PWM Denpasar',
            'PWM Palembang' => 'PWM Palembang',
            'PWM Padang' => 'PWM Padang',
            'PWM Medan' => 'PWM Medan',
            'PWM Pekanbaru' => 'PWM Pekanbaru',
        ];

        foreach ($salesMikaData as $row) {
            $mappedName = $orgNameMapping[$row->branch_name] ?? $row->branch_name;
            if (isset($results[$mappedName])) {
                $results[$mappedName]['sales_mika'] = (float)$row->total_sales;
            }
        }

        $salesSparepartQuery = "
            SELECT
                org.name as branch_name,
                SUM(CASE
                    WHEN SUBSTR(inv.documentno, 1, 3) = 'INC' THEN invl.linenetamt
                    WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN -invl.linenetamt
                    ELSE 0
                END) AS total_sales
            FROM c_invoice inv
            INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
            WHERE inv.ad_client_id = 1000001
                AND inv.issotrx = 'Y'
                AND invl.qtyinvoiced > 0
                AND invl.linenetamt > 0
                AND cat.name = 'SPARE PART'
                AND inv.docstatus IN ('CO', 'CL')
                AND inv.isactive = 'Y'
                AND inv.dateinvoiced >= ?
                AND inv.dateinvoiced <= ?
            GROUP BY org.name
        ";

        $salesSparepartData = DB::select($salesSparepartQuery, [$salesDateStart, $salesDateEnd]);

        foreach ($salesSparepartData as $row) {
            $mappedName = $orgNameMapping[$row->branch_name] ?? $row->branch_name;
            if (isset($results[$mappedName])) {
                $results[$mappedName]['sales_sparepart'] = (float)$row->total_sales;
            }
        }

        // Ambil data STOK dan BDP secara realtime dari database cabang
        foreach ($branchConfig as $branchName => $config) {
            $realtimeData = $this->queryBranchRealtimeData($branchName, $config, $stokBdpDate);

            if (isset($realtimeData['connection_failed']) && $realtimeData['connection_failed']) {
                $results[$branchName]['connection_failed'] = true;
            } else {
                $results[$branchName]['stok_mika'] = $realtimeData['stok_mika'];
                $results[$branchName]['stok_sparepart'] = $realtimeData['stok_sparepart'];
                $results[$branchName]['bdp_mika'] = $realtimeData['bdp_mika'];
                $results[$branchName]['bdp_sparepart'] = $realtimeData['bdp_sparepart'];
            }
        }

        return $results;
    }

    private function getBranchData($branchName, $date, $config)
    {
        $pricelistVersionId = $config['pricelist_version_id'];
        $locatorId = $config['locator_id'];
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';

        $query = "
            SELECT
                COALESCE(SUM(ss3.nominal_netto_mika), 0) AS nominal_netto_mika,
                COALESCE(SUM(ss3.nominal_netto_sparepart), 0) AS nominal_netto_sparepart,
                COALESCE(SUM(ss3.nilai_stok_mika), 0) AS nilai_stok_mika,
                COALESCE(SUM(ss3.nilai_stok_sparepart), 0) AS nilai_stok_sparepart,
                COALESCE(SUM(ss3.bdp_mika), 0) as bdp_mika,
                COALESCE(SUM(ss3.bdp_sparepart), 0) as bdp_sparepart
            FROM
            (
                --SALES MIKA NETTO (with new rules)
                SELECT
                    COALESCE(SUM(invl.linenetamt), 0) AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
                    0 AS nilai_stok_mika,
                    0 AS nilai_stok_sparepart,
                    0 AS bdp_mika,
                    0 AS bdp_sparepart
                FROM
                    c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                    INNER JOIN c_bpartner cust ON inv.c_bpartner_id = cust.c_bpartner_id
                    INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    inv.ad_client_id = 1000001
                    AND inv.issotrx = 'Y'
                    AND invl.qtyinvoiced > 0
                    AND invl.linenetamt > 0
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND inv.documentno LIKE 'INC%'
                    AND (
                        cat.value = 'MIKA'
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND prd.name NOT LIKE '%BOHLAM%'
                            AND psc.value = 'MIKA'
                        )
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND (
                                prd.name LIKE '%FILTER UDARA%'
                                OR prd.name LIKE '%SWITCH REM%'
                                OR prd.name LIKE '%DOP RITING%'
                            )
                        )
                    )
                    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                    AND org.name = ?
                    AND inv.dateinvoiced >= ?
                    AND inv.dateinvoiced <= ?

                UNION ALL

                --SALES SPARE PART NETTO
                SELECT
                    0 AS nominal_netto_mika,
                    SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) = 'INC' THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN -invl.linenetamt
                        ELSE 0
                    END) AS nominal_netto_sparepart,
                    0 AS nilai_stok_mika,
                    0 AS nilai_stok_sparepart,
                    0 AS bdp_mika,
                    0 AS bdp_sparepart
                FROM
                    c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                    INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                WHERE
                    inv.ad_client_id = 1000001
                    AND inv.issotrx = 'Y'
                    AND invl.qtyinvoiced > 0
                    AND invl.linenetamt > 0
                    AND cat.name = 'SPARE PART'
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name = ?
                    AND inv.dateinvoiced >= ?
                    AND inv.dateinvoiced <= ?

                UNION ALL

                --STOK MIKA (RP) (with new rules)
                SELECT
                    0 AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
                    COALESCE(SUM(stock_qty.qty_onhand * prc.pricelist * 0.615), 0) AS nilai_stok_mika,
                    0 AS nilai_stok_sparepart,
                    0 AS bdp_mika,
                    0 AS bdp_sparepart
                FROM
                    m_product prd
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                    INNER JOIN m_productprice prc ON prd.m_product_id = prc.m_product_id
                    INNER JOIN m_pricelist_version plv ON plv.m_pricelist_version_id = prc.m_pricelist_version_id
                    INNER JOIN ad_org org ON plv.ad_org_id = org.ad_org_id
                    LEFT JOIN (
                        SELECT
                            st.m_product_id,
                            SUM(st.qtyonhand) AS qty_onhand
                        FROM m_storage st
                        INNER JOIN m_locator loc ON st.m_locator_id = loc.m_locator_id
                        WHERE loc.m_locator_id = ?
                            AND st.qtyonhand > 0
                        GROUP BY st.m_product_id
                    ) stock_qty ON prd.m_product_id = stock_qty.m_product_id
                WHERE
                    (
                        cat.value = 'MIKA'
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND prd.name NOT LIKE '%BOHLAM%'
                            AND psc.value = 'MIKA'
                        )
                        OR (
                            cat.value = 'PRODUCT IMPORT' 
                            AND (
                                prd.name LIKE '%FILTER UDARA%'
                                OR prd.name LIKE '%SWITCH REM%'
                                OR prd.name LIKE '%DOP RITING%'
                            )
                        )
                    )
                    AND plv.m_pricelist_version_id = ?
                    AND org.name = ?

                UNION ALL

                --STOK SPARE PART (RP)
                SELECT
                    0 AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
                    0 AS nilai_stok_mika,
                    COALESCE(SUM(stock_qty.qty_onhand * prc.pricelist * 0.615), 0) AS nilai_stok_sparepart,
                    0 AS bdp_mika,
                    0 AS bdp_sparepart
                FROM
                    m_product prd
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    INNER JOIN m_productprice prc ON prd.m_product_id = prc.m_product_id
                    INNER JOIN m_pricelist_version plv ON plv.m_pricelist_version_id = prc.m_pricelist_version_id
                    INNER JOIN ad_org org ON plv.ad_org_id = org.ad_org_id
                    LEFT JOIN (
                        SELECT
                            st.m_product_id,
                            SUM(st.qtyonhand) AS qty_onhand
                        FROM m_storage st
                        INNER JOIN m_locator loc ON st.m_locator_id = loc.m_locator_id
                        WHERE loc.m_locator_id = ?
                            AND st.qtyonhand > 0
                        GROUP BY st.m_product_id
                    ) stock_qty ON prd.m_product_id = stock_qty.m_product_id
                WHERE
                    cat.name LIKE 'SPARE PART'
                    AND plv.m_pricelist_version_id = ?
                    AND org.name = ?

                UNION ALL

                --BDP MIKA (with new rules)
                SELECT
                    0 AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
                    0 AS nilai_stok_mika,
                    0 AS nilai_stok_sparepart,
                    COALESCE(SUM(ss2.nilai_bdp_mika), 0) AS nilai_bdp_mika,
                    0 AS nilai_bdp_sparepart
                FROM
                (
                    SELECT SUM((ss1.qtyinvoiced - ss1.qtymr) * ss1.priceactual) AS nilai_bdp_mika
                    FROM
                    (
                        SELECT
                            d.qtyinvoiced,
                            COALESCE(SUM(mi.qty), 0) AS qtymr,
                            d.priceactual
                        FROM
                            c_invoice h
                            INNER JOIN c_invoiceline d ON d.c_invoice_id = h.c_invoice_id
                            LEFT OUTER JOIN m_matchinv mi ON d.c_invoiceline_id = mi.c_invoiceline_id AND mi.created::date <= ?::date
                            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                            LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        WHERE
                            h.documentno LIKE 'INS-%'
                            AND h.docstatus = 'CO'
                            AND h.issotrx = 'N'
                            AND org.name = ?
                            AND (
                                cat.value = 'MIKA'
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            AND h.dateinvoiced >= ?
                            AND h.dateinvoiced <= ?
                        GROUP BY
                            cat.name, prd.name, d.c_invoiceline_id, d.c_invoice_id, h.documentno, d.qtyinvoiced, d.priceactual
                    ) AS ss1
                    WHERE ss1.qtyinvoiced <> ss1.qtymr
                ) AS ss2

                UNION ALL

                --BDP SPARE PART
                SELECT
                    0 AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
                    0 AS nilai_stok_mika,
                    0 AS nilai_stok_sparepart,
                    0 AS nilai_bdp_mika,
                    COALESCE(SUM(ss2.nilai_bdp_sparepart), 0) AS nilai_bdp_sparepart
                FROM
                (
                    SELECT SUM((ss1.qtyinvoiced - ss1.qtymr) * ss1.priceactual) AS nilai_bdp_sparepart
                    FROM
                    (
                        SELECT
                            d.qtyinvoiced,
                            COALESCE(SUM(mi.qty), 0) AS qtymr,
                            d.priceactual
                        FROM
                            c_invoice h
                            INNER JOIN c_invoiceline d ON d.c_invoice_id = h.c_invoice_id
                            LEFT OUTER JOIN m_matchinv mi ON d.c_invoiceline_id = mi.c_invoiceline_id AND mi.created::date <= ?::date
                            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        WHERE
                            h.documentno LIKE 'INS-%'
                            AND h.docstatus = 'CO'
                            AND h.issotrx = 'N'
                            AND org.name = ?
                            AND cat.name = 'SPARE PART'
                            AND h.dateinvoiced >= ?
                            AND h.dateinvoiced <= ?
                        GROUP BY
                            cat.name, prd.name, d.c_invoiceline_id, d.c_invoice_id, h.documentno, d.qtyinvoiced, d.priceactual
                    ) AS ss1
                    WHERE ss1.qtyinvoiced <> ss1.qtymr
                ) AS ss2
            ) AS ss3
        ";

        $result = DB::selectOne($query, [
            $branchName,
            $dateStart,
            $dateEnd,
            $branchName,
            $dateStart,
            $dateEnd,
            $locatorId,
            $pricelistVersionId,
            $branchName,
            $locatorId,
            $pricelistVersionId,
            $branchName,
            $date,
            $branchName,
            $dateStart,
            $dateEnd,
            $date,
            $branchName,
            $dateStart,
            $dateEnd
        ]);

        return [
            'sales_mika' => $result ? (float)$result->nominal_netto_mika : 0,
            'sales_sparepart' => $result ? (float)$result->nominal_netto_sparepart : 0,
            'stok_mika' => $result ? (float)$result->nilai_stok_mika : 0,
            'stok_sparepart' => $result ? (float)$result->nilai_stok_sparepart : 0,
            'bdp_mika' => $result ? (float)$result->bdp_mika : 0,
            'bdp_sparepart' => $result ? (float)$result->bdp_sparepart : 0,
        ];
    }

    public function exportExcel(Request $request)
    {
        try {
            $dataRequest = new Request([]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $totals = $responseData['totals'] ?? null;
            $formattedDate = $responseData['formatted_date'];

            $filename = 'Rekap_Sales_Stok_dan_BDP_' . str_replace(' ', '_', $formattedDate) . '.xls';

            $headers = [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $html = '
            <html xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Rekap Sales, Stok, dan BDP</x:Name>
                                <x:WorksheetOptions>
                                    <x:Print>
                                        <x:ValidPrinterInfo/>
                                    </x:Print>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    body { font-family: Verdana, sans-serif; }
                    table { border-collapse: collapse; }
                    th, td {
                        border: 1px solid #000;
                        padding: 6px 8px;
                        text-align: left;
                        font-family: Verdana, sans-serif;
                        font-size: 10pt;
                    }
                    th {
                        background-color: #D3D3D3;
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .title {
                        font-family: Verdana, sans-serif;
                        font-size: 16pt;
                        font-weight: bold;
                        margin-bottom: 8px;
                    }
                    .period {
                        font-family: Verdana, sans-serif;
                        font-size: 12pt;
                        margin-bottom: 15px;
                    }
                    .number { text-align: right; }
                </style>
            </head>
            <body>
                <div class="title">REKAP SALES, STOK, DAN BDP</div>
                <div class="period">Periode ' . htmlspecialchars($formattedDate) . '</div>
                <br>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">NO</th>
                            <th rowspan="2">CABANG</th>
                            <th colspan="3">SALES PER ' . htmlspecialchars($formattedDate) . '</th>
                            <th colspan="3">STOK PER ' . htmlspecialchars($formattedDate) . '</th>
                            <th colspan="3">BDP PER ' . htmlspecialchars($formattedDate) . '</th>
                            <th colspan="3">STOK+BDP PER ' . htmlspecialchars($formattedDate) . '</th>
                            <th colspan="3">SALES+STOK+BDP PER ' . htmlspecialchars($formattedDate) . '</th>
                        </tr>
                        <tr>
                            <th>MIKA</th>
                            <th>SPAREPART</th>
                            <th>TOTAL SALES</th>
                            <th>MIKA</th>
                            <th>SPAREPART</th>
                            <th>TOTAL STOK</th>
                            <th>MIKA</th>
                            <th>SPAREPART</th>
                            <th>TOTAL BDP</th>
                            <th>MIKA</th>
                            <th>SPAREPART</th>
                            <th>TOTAL STOK+BDP</th>
                            <th>MIKA</th>
                            <th>SPAREPART</th>
                            <th>TOTAL STOK+BDP+SALES</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .= '<tr>
                    <td style="text-align: right;">' . $item['no'] . '</td>
                    <td>' . htmlspecialchars($item['branch_code']) . '</td>
                    <td class="number">' . ($item['sales_mika'] == 0 ? '-' : 'Rp ' . number_format($item['sales_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['sales_sparepart'] == 0 ? '-' : 'Rp ' . number_format($item['sales_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_sales'] == 0 ? '-' : 'Rp ' . number_format($item['total_sales'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_mika'] == 0 ? '-' : 'Rp ' . number_format($item['stok_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_sparepart'] == 0 ? '-' : 'Rp ' . number_format($item['stok_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_stok'] == 0 ? '-' : 'Rp ' . number_format($item['total_stok'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['bdp_mika'] == 0 ? '-' : 'Rp ' . number_format($item['bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['bdp_sparepart'] == 0 ? '-' : 'Rp ' . number_format($item['bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_bdp'] == 0 ? '-' : 'Rp ' . number_format($item['total_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_bdp_mika'] == 0 ? '-' : 'Rp ' . number_format($item['stok_bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_bdp_sparepart'] == 0 ? '-' : 'Rp ' . number_format($item['stok_bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_stok_bdp'] == 0 ? '-' : 'Rp ' . number_format($item['total_stok_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_mika'] == 0 ? '-' : 'Rp ' . number_format($item['total_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_sparepart'] == 0 ? '-' : 'Rp ' . number_format($item['total_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['grand_total'] == 0 ? '-' : 'Rp ' . number_format($item['grand_total'], 0, '.', ',')) . '</td>
                </tr>';
            }

            if ($totals) {
                $html .= '<tr style="background-color: #F0F0F0; font-weight: bold; border-top: 2px solid #000;">
                    <td colspan="2" style="text-align: center;">TOTAL</td>
                    <td class="number">' . ($totals['sales_mika'] == 0 ? '-' : 'Rp ' . number_format($totals['sales_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['sales_sparepart'] == 0 ? '-' : 'Rp ' . number_format($totals['sales_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_sales'] == 0 ? '-' : 'Rp ' . number_format($totals['total_sales'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['stok_mika'] == 0 ? '-' : 'Rp ' . number_format($totals['stok_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['stok_sparepart'] == 0 ? '-' : 'Rp ' . number_format($totals['stok_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_stok'] == 0 ? '-' : 'Rp ' . number_format($totals['total_stok'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['bdp_mika'] == 0 ? '-' : 'Rp ' . number_format($totals['bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['bdp_sparepart'] == 0 ? '-' : 'Rp ' . number_format($totals['bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_bdp'] == 0 ? '-' : 'Rp ' . number_format($totals['total_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['stok_bdp_mika'] == 0 ? '-' : 'Rp ' . number_format($totals['stok_bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['stok_bdp_sparepart'] == 0 ? '-' : 'Rp ' . number_format($totals['stok_bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_stok_bdp'] == 0 ? '-' : 'Rp ' . number_format($totals['total_stok_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_mika'] == 0 ? '-' : 'Rp ' . number_format($totals['total_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['total_sparepart'] == 0 ? '-' : 'Rp ' . number_format($totals['total_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($totals['grand_total'] == 0 ? '-' : 'Rp ' . number_format($totals['grand_total'], 0, '.', ',')) . '</td>
                </tr>';
            }

            $html .= '
                    </tbody>
                </table>
                <br>
                <br>
                <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
            </body>
            </html>';

            return response($html, 200, $headers);
        } catch (\Exception $e) {
            TableHelper::logError('SalesComparisonController', 'exportExcel', $e, [
                'action' => 'export_excel'
            ]);

            return response()->json(['error' => 'Failed to export Excel file'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $dataRequest = new Request([]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $formattedDate = $responseData['formatted_date'];

            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    @page { margin: 20px; size: A4 landscape; }
                    body {
                        font-family: Verdana, sans-serif;
                        font-size: 6pt;
                        margin: 0;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .title {
                        font-family: Verdana, sans-serif;
                        font-size: 14pt;
                        font-weight: bold;
                        margin-bottom: 5px;
                    }
                    .period {
                        font-family: Verdana, sans-serif;
                        font-size: 10pt;
                        color: #666;
                        margin-bottom: 20px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    th, td {
                        border: 1px solid #ddd;
                        padding: 3px 4px;
                        text-align: left;
                        font-family: Verdana, sans-serif;
                        font-size: 5pt;
                    }
                    th {
                        background-color: #F5F5F5;
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .number { text-align: right; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="title">REKAP SALES, STOK, DAN BDP</div>
                    <div class="period">Periode ' . htmlspecialchars($formattedDate) . '</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">NO</th>
                            <th rowspan="2">CABANG</th>
                            <th colspan="3">SALES</th>
                            <th colspan="3">STOK</th>
                            <th colspan="3">BDP</th>
                            <th colspan="3">STOK+BDP</th>
                            <th colspan="3">SALES+STOK+BDP</th>
                        </tr>
                        <tr>
                            <th>MIKA</th>
                            <th>SP</th>
                            <th>TOTAL</th>
                            <th>MIKA</th>
                            <th>SP</th>
                            <th>TOTAL</th>
                            <th>MIKA</th>
                            <th>SP</th>
                            <th>TOTAL</th>
                            <th>MIKA</th>
                            <th>SP</th>
                            <th>TOTAL</th>
                            <th>MIKA</th>
                            <th>SP</th>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .= '<tr>
                    <td style="text-align: right;">' . $item['no'] . '</td>
                    <td>' . htmlspecialchars($item['branch_code']) . '</td>
                    <td class="number">' . ($item['sales_mika'] == 0 ? '-' : number_format($item['sales_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['sales_sparepart'] == 0 ? '-' : number_format($item['sales_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_sales'] == 0 ? '-' : number_format($item['total_sales'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_mika'] == 0 ? '-' : number_format($item['stok_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_sparepart'] == 0 ? '-' : number_format($item['stok_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_stok'] == 0 ? '-' : number_format($item['total_stok'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['bdp_mika'] == 0 ? '-' : number_format($item['bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['bdp_sparepart'] == 0 ? '-' : number_format($item['bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_bdp'] == 0 ? '-' : number_format($item['total_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_bdp_mika'] == 0 ? '-' : number_format($item['stok_bdp_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['stok_bdp_sparepart'] == 0 ? '-' : number_format($item['stok_bdp_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_stok_bdp'] == 0 ? '-' : number_format($item['total_stok_bdp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_mika'] == 0 ? '-' : number_format($item['total_mika'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['total_sparepart'] == 0 ? '-' : number_format($item['total_sparepart'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['grand_total'] == 0 ? '-' : number_format($item['grand_total'], 0, '.', ',')) . '</td>
                </tr>';
            }

            $html .= '
                    </tbody>
                </table>
                <br>
                <br>
                <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
            </body>
            </html>';

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'Rekap_Sales_Stok_dan_BDP_' . str_replace(' ', '_', $formattedDate) . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError('SalesComparisonController', 'exportPdf', $e, [
                'action' => 'export_pdf'
            ]);

            return response()->json(['error' => 'Failed to export PDF file'], 500);
        }
    }
}

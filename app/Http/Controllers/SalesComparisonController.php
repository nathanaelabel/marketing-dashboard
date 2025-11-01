<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TableHelper;

class SalesComparisonController extends Controller
{
    // Branch-specific configuration for pricelist and locator
    private function getBranchConfig()
    {
        return [
            'PWM Pontianak' => ['pricelist_version_id' => 120000004, 'locator_id' => 1000010],
            'PWM Medan' => ['pricelist_version_id' => 120000001, 'locator_id' => 1000001],
            'PWM Makassar' => ['pricelist_version_id' => 120000006, 'locator_id' => 1000006],
            'PWM Palembang' => ['pricelist_version_id' => 120000007, 'locator_id' => 1000007],
            'PWM Denpasar' => ['pricelist_version_id' => 120000008, 'locator_id' => 1000008],
            'PWM Surabaya' => ['pricelist_version_id' => 120000009, 'locator_id' => 1000009],
            'PWM Pekanbaru' => ['pricelist_version_id' => 120000005, 'locator_id' => 1000005],
            'PWM Cirebon' => ['pricelist_version_id' => 120000013, 'locator_id' => 1000013],
            'MPM Tangerang' => ['pricelist_version_id' => 120000000, 'locator_id' => 1000000],
            'PWM Bekasi' => ['pricelist_version_id' => 120000002, 'locator_id' => 1000002],
            'PWM Semarang' => ['pricelist_version_id' => 120000012, 'locator_id' => 1000012],
            'PWM Banjarmasin' => ['pricelist_version_id' => 120000014, 'locator_id' => 1000014],
            'PWM Bandung' => ['pricelist_version_id' => 120000003, 'locator_id' => 1000003],
            'PWM Lampung' => ['pricelist_version_id' => 120000015, 'locator_id' => 1000015],
            'PWM Jakarta' => ['pricelist_version_id' => 120000011, 'locator_id' => 1000011],
            'PWM Purwokerto' => ['pricelist_version_id' => 120000016, 'locator_id' => 1000016],
            'PWM Padang' => ['pricelist_version_id' => 120000017, 'locator_id' => 1000017],
        ];
    }

    public function index()
    {
        return view('sales-comparison');
    }

    public function getData(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));

            // Validate date
            if (!strtotime($date)) {
                return response()->json(['error' => 'Invalid date parameter'], 400);
            }

            // Prevent future dates
            if (strtotime($date) > strtotime('today')) {
                return response()->json(['error' => 'Future dates are not allowed'], 400);
            }

            // Get all branch codes from TableHelper
            $branchMapping = TableHelper::getBranchMapping();
            $branchConfig = $this->getBranchConfig();

            $data = [];
            $no = 1;

            // Iterate through each branch
            foreach ($branchMapping as $branchName => $branchCode) {
                if (!isset($branchConfig[$branchName])) {
                    continue; // Skip if no config for this branch
                }

                $config = $branchConfig[$branchName];

                // Get all data for the branch
                $branchData = $this->getBranchData($branchName, $date, $config);

                // Calculate totals
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
            }

            return response()->json([
                'data' => $data,
                'date' => $date,
                'formatted_date' => date('d F Y', strtotime($date)),
                'total_count' => count($data)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('SalesComparisonController', 'getData', $e, [
                'date' => $request->get('date')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getBranchData($branchName, $date, $config)
    {
        $pricelistVersionId = $config['pricelist_version_id'];
        $locatorId = $config['locator_id'];

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
                --SALES MIKA NETTO
                SELECT
                    SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
                    END) AS nominal_netto_mika,
                    0 AS nominal_netto_sparepart,
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
                    AND cat.value = 'MIKA'
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name = ?
                    AND DATE(inv.dateinvoiced) = ?

                UNION ALL

                --SALES SPARE PART NETTO
                SELECT
                    0 AS nominal_netto_mika,
                    SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
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
                    AND cat.value = 'SPARE PART'
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name = ?
                    AND DATE(inv.dateinvoiced) = ?

                UNION ALL

                --STOK MIKA (RP)
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
                    cat.name LIKE 'MIKA'
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

                --BDP MIKA
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
                            LEFT OUTER JOIN m_matchinv mi ON d.c_invoiceline_id = mi.c_invoiceline_id
                            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        WHERE
                            h.documentno LIKE 'INS-%'
                            AND h.docstatus = 'CO'
                            AND h.issotrx = 'N'
                            AND org.name = ?
                            AND cat.name = 'MIKA'
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
                            LEFT OUTER JOIN m_matchinv mi ON d.c_invoiceline_id = mi.c_invoiceline_id
                            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        WHERE
                            h.documentno LIKE 'INS-%'
                            AND h.docstatus = 'CO'
                            AND h.issotrx = 'N'
                            AND org.name = ?
                            AND cat.name = 'SPARE PART'
                        GROUP BY
                            cat.name, prd.name, d.c_invoiceline_id, d.c_invoice_id, h.documentno, d.qtyinvoiced, d.priceactual
                    ) AS ss1
                    WHERE ss1.qtyinvoiced <> ss1.qtymr
                ) AS ss2
            ) AS ss3
        ";

        $result = DB::selectOne($query, [
            $branchName,
            $date,  // Sales MIKA
            $branchName,
            $date,  // Sales SPARE PART
            $locatorId,
            $pricelistVersionId,
            $branchName,  // Stok MIKA
            $locatorId,
            $pricelistVersionId,
            $branchName,  // Stok SPARE PART
            $branchName,  // BDP MIKA
            $branchName   // BDP SPARE PART
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
            $date = $request->get('date', date('Y-m-d'));

            // Validate date
            if (!strtotime($date)) {
                return response()->json(['error' => 'Invalid date parameter'], 400);
            }

            // Get all data
            $dataRequest = new Request(['date' => $date]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $formattedDate = $responseData['formatted_date'];

            $filename = 'Sales_Comparison_' . str_replace(' ', '_', $formattedDate) . '.xls';

            // Create XLS content using HTML table format
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
                                <x:Name>Sales Comparison</x:Name>
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
                <div class="title">PERBANDINGAN SALES, STOK, DAN BDP</div>
                <div class="period">Date: ' . htmlspecialchars($formattedDate) . '</div>
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
                'date' => $request->get('date')
            ]);

            return response()->json(['error' => 'Failed to export Excel file'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));

            // Validate date
            if (!strtotime($date)) {
                return response()->json(['error' => 'Invalid date parameter'], 400);
            }

            // Get all data
            $dataRequest = new Request(['date' => $date]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $formattedDate = $responseData['formatted_date'];

            // Create HTML for PDF
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
                    <div class="title">PERBANDINGAN SALES, STOK, DAN BDP</div>
                    <div class="period">Date: ' . htmlspecialchars($formattedDate) . '</div>
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

            // Use DomPDF to generate PDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'Sales_Comparison_' . str_replace(' ', '_', $formattedDate) . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError('SalesComparisonController', 'exportPdf', $e, [
                'date' => $request->get('date')
            ]);

            return response()->json(['error' => 'Failed to export PDF file'], 500);
        }
    }
}

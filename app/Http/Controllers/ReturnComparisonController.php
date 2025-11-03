<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TableHelper;

class ReturnComparisonController extends Controller
{
    public function index()
    {
        return view('return-comparison');
    }

    public function getData(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Get all branch codes from TableHelper
            $branchMapping = TableHelper::getBranchMapping();
            $branchCodes = TableHelper::getBranchCodes();

            $data = [];
            $no = 1;

            // Iterate through each branch
            foreach ($branchMapping as $branchName => $branchCode) {
                // Get Sales Bruto
                $salesBruto = $this->getSalesBruto($branchName, $month, $year);

                // Get CNC FX009 data
                $cncData = $this->getCNCData($branchName, $month, $year);

                // Get Barang FX016 data
                $barangData = $this->getBarangData($branchName, $month, $year);

                // Get Cabang Ke Pabrik data
                $cabangPabrikData = $this->getCabangPabrikData($branchName, $month, $year);

                // Calculate percentages
                $cncPercent = $salesBruto['rp'] > 0 ? ($cncData['rp'] / $salesBruto['rp']) * 100 : 0;
                $barangPercent = $salesBruto['rp'] > 0 ? ($barangData['rp'] / $salesBruto['rp']) * 100 : 0;
                $cabangPabrikPercent = $salesBruto['rp'] > 0 ? ($cabangPabrikData['rp'] / $salesBruto['rp']) * 100 : 0;

                $data[] = [
                    'no' => $no++,
                    'branch_code' => $branchCode,
                    'branch_name' => $branchName,
                    'sales_bruto_pc' => $salesBruto['pc'],
                    'sales_bruto_rp' => $salesBruto['rp'],
                    'cnc_pc' => $cncData['pc'],
                    'cnc_rp' => $cncData['rp'],
                    'cnc_percent' => $cncPercent,
                    'barang_pc' => $barangData['pc'],
                    'barang_rp' => $barangData['rp'],
                    'barang_percent' => $barangPercent,
                    'cabang_pabrik_pc' => $cabangPabrikData['pc'],
                    'cabang_pabrik_rp' => $cabangPabrikData['rp'],
                    'cabang_pabrik_percent' => $cabangPabrikPercent,
                ];
            }

            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json([
                'data' => $data,
                'period' => $period,
                'total_count' => count($data)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('ReturnComparisonController', 'getData', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getSalesBruto($branchName, $month, $year)
    {
        $query = "
            SELECT
                COALESCE(SUM(invl.qtyinvoiced), 0) as total_qty,
                COALESCE(SUM(invl.linenetamt), 0) as total_rp
            FROM c_invoice inv
            INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            WHERE inv.ad_client_id = 1000001
                AND inv.issotrx = 'Y'
                AND invl.qtyinvoiced > 0
                AND invl.linenetamt > 0
                AND inv.docstatus IN ('CO', 'CL')
                AND inv.isactive = 'Y'
                AND org.name = ?
                AND EXTRACT(year FROM inv.dateinvoiced) = ?
                AND EXTRACT(month FROM inv.dateinvoiced) = ?
        ";

        $result = DB::selectOne($query, [$branchName, $year, $month]);

        return [
            'pc' => $result ? (float)$result->total_qty : 0,
            'rp' => $result ? (float)$result->total_rp : 0
        ];
    }

    private function getCNCData($branchName, $month, $year)
    {
        $query = "
            SELECT
                COALESCE(SUM(d.qtyinvoiced), 0) as total_qty,
                COALESCE(SUM(d.linenetamt), 0) as total_rp
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE h.documentno LIKE 'CNC%'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND EXTRACT(year FROM h.dateinvoiced) = ?
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND org.name = ?
        ";

        $result = DB::selectOne($query, [$year, $month, $branchName]);

        return [
            'pc' => $result ? (float)$result->total_qty : 0,
            'rp' => $result ? (float)$result->total_rp : 0
        ];
    }

    private function getBarangData($branchName, $month, $year)
    {
        $query = "
            SELECT
                COALESCE(SUM(miol.movementqty), 0) as total_qty,
                COALESCE(SUM(col.priceactual * miol.movementqty), 0) as total_nominal
            FROM m_inoutline miol
            INNER JOIN m_inout mio ON miol.m_inout_id = mio.m_inout_id
            INNER JOIN c_orderline col ON miol.c_orderline_id = col.c_orderline_id
            INNER JOIN c_order co ON col.c_order_id = co.c_order_id
            LEFT OUTER JOIN c_invoiceline cil ON col.c_orderline_id = col.c_orderline_id
            INNER JOIN ad_org org ON miol.ad_org_id = org.ad_org_id
            WHERE org.name = ?
                AND co.documentno LIKE 'SOC%'
                AND co.docstatus = 'CL'
                AND mio.documentno LIKE 'SJC%'
                AND mio.docstatus IN ('CO', 'CL')
                AND EXTRACT(year FROM mio.movementdate) = ?
                AND EXTRACT(month FROM mio.movementdate) = ?
                AND cil.c_invoiceline_id IS NULL
        ";

        $result = DB::selectOne($query, [$branchName, $year, $month]);

        return [
            'pc' => $result ? (float)$result->total_qty : 0,
            'rp' => $result ? (float)$result->total_nominal : 0
        ];
    }

    private function getCabangPabrikData($branchName, $month, $year)
    {
        $query = "
            SELECT
                COALESCE(SUM(invl.qtyinvoiced), 0) as total_qty,
                COALESCE(SUM(invl.linenetamt), 0) as total_nominal
            FROM c_invoiceline invl
            INNER JOIN m_inoutline shpln ON invl.m_inoutline_id = shpln.m_inoutline_id
            INNER JOIN m_inout shp ON shpln.m_inout_id = shp.m_inout_id
            INNER JOIN c_invoice inv ON invl.c_invoice_id = inv.c_invoice_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            WHERE inv.issotrx = 'N'
                AND inv.docstatus IN ('CO','CL')
                AND inv.isactive = 'Y'
                AND shp.docstatus IN ('CO', 'CL')
                AND (inv.documentno LIKE 'DNS-%' OR inv.documentno LIKE 'NCS-%')
                AND org.name = ?
                AND EXTRACT(year FROM inv.dateinvoiced) = ?
                AND EXTRACT(month FROM inv.dateinvoiced) = ?
        ";

        $result = DB::selectOne($query, [$branchName, $year, $month]);

        return [
            'pc' => $result ? (float)$result->total_qty : 0,
            'rp' => $result ? (float)$result->total_nominal : 0
        ];
    }

    public function exportExcel(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Get all data
            $dataRequest = new Request(['month' => $month, 'year' => $year]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $period = $responseData['period'];

            $filename = 'Return_Comparison_' . str_replace(' ', '_', $period['month_name'] . '_' . $year) . '.xls';

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
                                <x:Name>Return Comparison</x:Name>
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
                <div class="title">PERBANDINGAN RETUR RUSAK</div>
                <div class="period">Period: ' . htmlspecialchars($period['month_name'] . ' ' . $year) . '</div>
                <br>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 60px;">NO</th>
                            <th rowspan="2" style="width: 150px;">CABANG</th>
                            <th colspan="2" style="width: 200px;">SALES BRUTO</th>
                            <th colspan="3" style="width: 300px;">CUST. KE CABANG (CNC) (FX009)</th>
                            <th colspan="3" style="width: 300px;">CUST. KE CABANG (BARANG) (FX016)</th>
                            <th colspan="3" style="width: 300px;">CABANG KE PABRIK</th>
                        </tr>
                        <tr>
                            <th style="width: 100px;">PC</th>
                            <th style="width: 150px;">RP</th>
                            <th style="width: 100px;">PC</th>
                            <th style="width: 150px;">RP</th>
                            <th style="width: 80px;">%</th>
                            <th style="width: 100px;">PC</th>
                            <th style="width: 150px;">RP</th>
                            <th style="width: 80px;">%</th>
                            <th style="width: 100px;">PC</th>
                            <th style="width: 150px;">RP</th>
                            <th style="width: 80px;">%</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .= '<tr>
                    <td style="text-align: right;">' . $item['no'] . '</td>
                    <td>' . htmlspecialchars($item['branch_code']) . '</td>
                    <td class="number">' . ($item['sales_bruto_pc'] == 0 ? '-' : number_format($item['sales_bruto_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['sales_bruto_rp'] == 0 ? '-' : 'Rp ' . number_format($item['sales_bruto_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_pc'] == 0 ? '-' : number_format($item['cnc_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_rp'] == 0 ? '-' : 'Rp ' . number_format($item['cnc_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_percent'] == 0 ? '-' : number_format($item['cnc_percent'], 2, '.', ',') . '%') . '</td>
                    <td class="number">' . ($item['barang_pc'] == 0 ? '-' : number_format($item['barang_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['barang_rp'] == 0 ? '-' : 'Rp ' . number_format($item['barang_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['barang_percent'] == 0 ? '-' : number_format($item['barang_percent'], 2, '.', ',') . '%') . '</td>
                    <td class="number">' . ($item['cabang_pabrik_pc'] == 0 ? '-' : number_format($item['cabang_pabrik_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cabang_pabrik_rp'] == 0 ? '-' : 'Rp ' . number_format($item['cabang_pabrik_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cabang_pabrik_percent'] == 0 ? '-' : number_format($item['cabang_pabrik_percent'], 2, '.', ',') . '%') . '</td>
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
            TableHelper::logError('ReturnComparisonController', 'exportExcel', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year')
            ]);

            return response()->json(['error' => 'Failed to export Excel file'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Get all data
            $dataRequest = new Request(['month' => $month, 'year' => $year]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData['error'])) {
                return response()->json(['error' => 'Failed to fetch data'], 500);
            }

            $data = $responseData['data'];
            $period = $responseData['period'];

            // Create HTML for PDF
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    @page { margin: 20px; }
                    body {
                        font-family: Verdana, sans-serif;
                        font-size: 7pt;
                        margin: 0;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .title {
                        font-family: Verdana, sans-serif;
                        font-size: 16pt;
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
                        padding: 4px 6px;
                        text-align: left;
                        font-family: Verdana, sans-serif;
                        font-size: 6pt;
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
                    <div class="title">PERBANDINGAN RETUR RUSAK</div>
                    <div class="period">Period: ' . htmlspecialchars($period['month_name'] . ' ' . $year) . '</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 30px;">NO</th>
                            <th rowspan="2" style="width: 80px;">CABANG</th>
                            <th colspan="2" style="width: 100px;">SALES BRUTO</th>
                            <th colspan="3" style="width: 150px;">CUST. KE CABANG (CNC) (FX009)</th>
                            <th colspan="3" style="width: 150px;">CUST. KE CABANG (BARANG) (FX016)</th>
                            <th colspan="3" style="width: 150px;">CABANG KE PABRIK</th>
                        </tr>
                        <tr>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .= '<tr>
                    <td style="text-align: right;">' . $item['no'] . '</td>
                    <td>' . htmlspecialchars($item['branch_code']) . '</td>
                    <td class="number">' . ($item['sales_bruto_pc'] == 0 ? '-' : number_format($item['sales_bruto_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['sales_bruto_rp'] == 0 ? '-' : 'Rp ' . number_format($item['sales_bruto_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_pc'] == 0 ? '-' : number_format($item['cnc_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_rp'] == 0 ? '-' : 'Rp ' . number_format($item['cnc_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cnc_percent'] == 0 ? '-' : number_format($item['cnc_percent'], 2, '.', ',') . '%') . '</td>
                    <td class="number">' . ($item['barang_pc'] == 0 ? '-' : number_format($item['barang_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['barang_rp'] == 0 ? '-' : 'Rp ' . number_format($item['barang_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['barang_percent'] == 0 ? '-' : number_format($item['barang_percent'], 2, '.', ',') . '%') . '</td>
                    <td class="number">' . ($item['cabang_pabrik_pc'] == 0 ? '-' : number_format($item['cabang_pabrik_pc'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cabang_pabrik_rp'] == 0 ? '-' : 'Rp ' . number_format($item['cabang_pabrik_rp'], 0, '.', ',')) . '</td>
                    <td class="number">' . ($item['cabang_pabrik_percent'] == 0 ? '-' : number_format($item['cabang_pabrik_percent'], 2, '.', ',') . '%') . '</td>
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

            $filename = 'Return_Comparison_' . str_replace(' ', '_', $period['month_name'] . '_' . $year) . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError('ReturnComparisonController', 'exportPdf', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year')
            ]);

            return response()->json(['error' => 'Failed to export PDF file'], 500);
        }
    }
}

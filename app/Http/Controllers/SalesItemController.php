<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TableHelper;

class SalesItemController extends Controller
{
    public function index()
    {
        return view('sales-item');
    }

    public function getData(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));
            $type = $request->get('type', 'rp'); // 'rp' or 'pcs'

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Validate type parameter
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Get all data at once for client-side pagination
            $branchData = $this->getAllSalesItemData($month, $year, $type);

            // Transform data using TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json([
                'data' => $transformedData,
                'period' => $period,
                'type' => $type,
                'total_count' => count($transformedData)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('SalesItemController', 'getData', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'type' => $request->get('type')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getAllSalesItemData($month, $year, $type)
    {
        // Choose field based on type
        $valueField = $type === 'pcs' ? 'qtyinvoiced' : 'linenetamt';
        $totalField = $type === 'pcs' ? 'total_qty' : 'total_net';

        // Get all sales data at once for client-side pagination
        $salesQuery = "
            SELECT
                org.name as branch_name,
                prd.name as product_name,
                prd.status as product_status,
                " . TableHelper::getValueCalculation($valueField) . " AS {$totalField}
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND d.linenetamt > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
            GROUP BY org.name, prd.name, prd.status
            ORDER BY prd.name
        ";

        return DB::select($salesQuery, [$month, $year]);
    }

    public function exportExcel(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));
            $type = $request->get('type', 'rp');

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Validate type parameter
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Get all data for export
            $branchData = $this->getAllSalesItemData($month, $year, $type);

            // Transform data using TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            $period = TableHelper::formatPeriodInfo($month, $year);
            $typeLabel = $type === 'pcs' ? 'Pieces' : 'Rupiah';

            $filename = 'Penjualan_Per_Item_' . str_replace(' ', '_', $period['month_name'] . '_' . $year) . '_' . $typeLabel . '.xls';

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
                                <x:Name>Penjualan Per Item</x:Name>
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
                <div class="title">PENJUALAN PER ITEM</div>
                <div class="period">Periode ' . htmlspecialchars($period['month_name'] . ' ' . $year) . ' | Tipe ' . htmlspecialchars($typeLabel) . '</div>
                <br>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 90px; text-align: right;">NO</th>
                            <th style="width: 400px;">NAMA BARANG</th>
                            <th style="width: 120px;">KET PL</th>
                            <th style="width: 250px;">TGR</th>
                            <th style="width: 250px;">BKS</th>
                            <th style="width: 250px;">JKT</th>
                            <th style="width: 250px;">PTK</th>
                            <th style="width: 250px;">LMP</th>
                            <th style="width: 250px;">BJM</th>
                            <th style="width: 250px;">CRB</th>
                            <th style="width: 250px;">BDG</th>
                            <th style="width: 250px;">MKS</th>
                            <th style="width: 250px;">SBY</th>
                            <th style="width: 250px;">SMG</th>
                            <th style="width: 250px;">PWT</th>
                            <th style="width: 250px;">DPS</th>
                            <th style="width: 250px;">PLB</th>
                            <th style="width: 250px;">PDG</th>
                            <th style="width: 250px;">MDN</th>
                            <th style="width: 250px;">PKU</th>
                            <th style="width: 270px;">NASIONAL</th>
                        </tr>
                    </thead>
                    <tbody>';

            $no = 1;
            foreach ($transformedData as $item) {
                $formatValue = function ($value) use ($type) {
                    if ($value == 0) return '-';
                    return $type === 'rp' ? 'Rp ' . number_format($value, 0, '.', ',') : number_format($value, 0, '.', ',');
                };

                $html .= '<tr>
                    <td style="text-align: center;">' . $no++ . '</td>
                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($item['product_status'] ?: '-') . '</td>
                    <td class="number">' . $formatValue($item['tgr']) . '</td>
                    <td class="number">' . $formatValue($item['bks']) . '</td>
                    <td class="number">' . $formatValue($item['jkt']) . '</td>
                    <td class="number">' . $formatValue($item['ptk']) . '</td>
                    <td class="number">' . $formatValue($item['lmp']) . '</td>
                    <td class="number">' . $formatValue($item['bjm']) . '</td>
                    <td class="number">' . $formatValue($item['crb']) . '</td>
                    <td class="number">' . $formatValue($item['bdg']) . '</td>
                    <td class="number">' . $formatValue($item['mks']) . '</td>
                    <td class="number">' . $formatValue($item['sby']) . '</td>
                    <td class="number">' . $formatValue($item['smg']) . '</td>
                    <td class="number">' . $formatValue($item['pwt']) . '</td>
                    <td class="number">' . $formatValue($item['dps']) . '</td>
                    <td class="number">' . $formatValue($item['plb']) . '</td>
                    <td class="number">' . $formatValue($item['pdg']) . '</td>
                    <td class="number">' . $formatValue($item['mdn']) . '</td>
                    <td class="number">' . $formatValue($item['pku']) . '</td>
                    <td class="number"><strong>' . $formatValue($item['nasional']) . '</strong></td>
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
            TableHelper::logError('SalesItemController', 'exportExcel', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'type' => $request->get('type')
            ]);

            return response()->json(['error' => 'Failed to export Excel file'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));
            $type = $request->get('type', 'rp');

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Validate type parameter
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Get all data for export
            $branchData = $this->getAllSalesItemData($month, $year, $type);

            // Transform data using TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            $period = TableHelper::formatPeriodInfo($month, $year);
            $typeLabel = $type === 'pcs' ? 'Pieces' : 'Rupiah';

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
                        font-size: 8pt;
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
                        font-size: 7pt;
                    }
                    th {
                        background-color: #F5F5F5;
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .number { text-align: right; }
                    .product-name {
                        max-width: 200px;
                        word-wrap: break-word;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="title">PENJUALAN PER ITEM</div>
                    <div class="period">Periode ' . htmlspecialchars($period['month_name'] . ' ' . $year) . ' | Tipe ' . htmlspecialchars($typeLabel) . '</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px; text-align: right;">NO</th>
                            <th style="width: 200px;">NAMA BARANG</th>
                            <th style="width: 120px;">KET PL</th>
                            <th style="width: 60px;">TGR</th>
                            <th style="width: 60px;">BKS</th>
                            <th style="width: 60px;">JKT</th>
                            <th style="width: 60px;">PTK</th>
                            <th style="width: 60px;">LMP</th>
                            <th style="width: 60px;">BJM</th>
                            <th style="width: 60px;">CRB</th>
                            <th style="width: 60px;">BDG</th>
                            <th style="width: 60px;">MKS</th>
                            <th style="width: 60px;">SBY</th>
                            <th style="width: 60px;">SMG</th>
                            <th style="width: 60px;">PWT</th>
                            <th style="width: 60px;">DPS</th>
                            <th style="width: 60px;">PLB</th>
                            <th style="width: 60px;">PDG</th>
                            <th style="width: 60px;">MDN</th>
                            <th style="width: 60px;">PKU</th>
                            <th style="width: 80px;">NASIONAL</th>
                        </tr>
                    </thead>
                    <tbody>';

            $no = 1;
            foreach ($transformedData as $item) {
                $formatValue = function ($value) use ($type) {
                    if ($value == 0) return '-';
                    return $type === 'rp' ? 'Rp ' . number_format($value, 0, '.', ',') : number_format($value, 0, '.', ',');
                };

                $html .= '<tr>
                    <td style="text-align: right;">' . $no++ . '</td>
                    <td class="product-name">' . htmlspecialchars($item['product_name']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($item['product_status'] ?: '-') . '</td>
                    <td class="number">' . $formatValue($item['tgr']) . '</td>
                    <td class="number">' . $formatValue($item['bks']) . '</td>
                    <td class="number">' . $formatValue($item['jkt']) . '</td>
                    <td class="number">' . $formatValue($item['ptk']) . '</td>
                    <td class="number">' . $formatValue($item['lmp']) . '</td>
                    <td class="number">' . $formatValue($item['bjm']) . '</td>
                    <td class="number">' . $formatValue($item['crb']) . '</td>
                    <td class="number">' . $formatValue($item['bdg']) . '</td>
                    <td class="number">' . $formatValue($item['mks']) . '</td>
                    <td class="number">' . $formatValue($item['sby']) . '</td>
                    <td class="number">' . $formatValue($item['smg']) . '</td>
                    <td class="number">' . $formatValue($item['pwt']) . '</td>
                    <td class="number">' . $formatValue($item['dps']) . '</td>
                    <td class="number">' . $formatValue($item['plb']) . '</td>
                    <td class="number">' . $formatValue($item['pdg']) . '</td>
                    <td class="number">' . $formatValue($item['mdn']) . '</td>
                    <td class="number">' . $formatValue($item['pku']) . '</td>
                    <td class="number"><strong>' . $formatValue($item['nasional']) . '</strong></td>
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

            $filename = 'Penjualan_Per_Item_' . str_replace(' ', '_', $period['month_name'] . '_' . $year) . '_' . $typeLabel . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError('SalesItemController', 'exportPdf', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'type' => $request->get('type')
            ]);

            return response()->json(['error' => 'Failed to export PDF file'], 500);
        }
    }
}

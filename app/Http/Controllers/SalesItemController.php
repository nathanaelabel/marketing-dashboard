<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Gunakan H-1 karena dashboard diupdate setiap hari
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', $yesterday);
            $type = $request->get('type', 'rp');

            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            // Cek apakah tanggal mulai setelah tanggal akhir
            if ($start->gt($end)) {
                return response()->json([
                    'error' => 'Invalid date range',
                    'message' => 'Start date must be before or equal to end date'
                ], 400);
            }

            // Cek apakah rentang tanggal terlalu besar (maks 1 tahun)
            $daysDiff = $start->diffInDays($end);
            if ($daysDiff > 365) {
                return response()->json([
                    'error' => 'Date range too large',
                    'message' => 'Maximum date range is 1 year (365 days). Please select a smaller date range.'
                ], 400);
            }

            // Cek apakah tanggal terlalu jauh di masa lalu (sebelum 2020)
            if ($start->year < 2020) {
                return response()->json([
                    'error' => 'Invalid date range',
                    'message' => 'Start date cannot be before year 2020'
                ], 400);
            }

            if ($end->isFuture()) {
                return response()->json([
                    'error' => 'Invalid date range',
                    'message' => 'End date cannot be in the future'
                ], 400);
            }

            // Ambil semua data sekaligus untuk pagination sisi klien
            $branchData = $this->getAllSalesItemData($startDate, $endDate, $type);

            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
            $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
            $period = [
                'start_date' => $formattedStartDate,
                'end_date' => $formattedEndDate,
                'display' => $formattedStartDate . ' - ' . $formattedEndDate
            ];

            return response()->json([
                'data' => $transformedData,
                'period' => $period,
                'type' => $type,
                'total_count' => count($transformedData)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('SalesItemController', 'getData', $e, [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'type' => $request->get('type')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getAllSalesItemData($startDate, $endDate, $type)
    {
        // Pilih field berdasarkan type
        $valueField = $type === 'pcs' ? 'qtyinvoiced' : 'linenetamt';
        $totalField = $type === 'pcs' ? 'total_qty' : 'total_net';

        // Ambil semua data penjualan sekaligus
        $salesQuery = "
            SELECT
                org.name as branch_name,
                prd.name as product_name,
                prd.status as product_status,
                " . TableHelper::getValueCalculation($valueField) . " AS {$totalField}
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND d.linenetamt > 0
                AND DATE(h.dateinvoiced) BETWEEN ? AND ?
                AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
            GROUP BY org.name, prd.name, prd.status
            ORDER BY prd.name
        ";

        return DB::select($salesQuery, [$startDate, $endDate]);
    }

    public function exportExcel(Request $request)
    {
        try {
            // Gunakan H-1 karena dashboard diupdate setiap hari
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', $yesterday);
            $type = $request->get('type', 'rp');

            // Validasi parameter type
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Ambil semua data untuk export
            $branchData = $this->getAllSalesItemData($startDate, $endDate, $type);

            // Transform data menggunakan TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            // Format tanggal untuk nama file dan tampilan
            $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
            $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
            $fileStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
            $fileEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
            $typeLabel = $type === 'pcs' ? 'Pieces' : 'Rupiah';

            $filename = 'Penjualan_Per_Item_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.xls';

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
                <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
                <div class="period">Tipe ' . htmlspecialchars($typeLabel) . '</div>
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
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'type' => $request->get('type')
            ]);

            return response()->json(['error' => 'Failed to export Excel file'], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            // Gunakan H-1 karena dashboard diupdate setiap hari
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', $yesterday);
            $type = $request->get('type', 'rp');

            // Validasi parameter type
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Ambil semua data untuk export
            $branchData = $this->getAllSalesItemData($startDate, $endDate, $type);

            // Transform data menggunakan TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            // Format tanggal untuk nama file dan tampilan
            $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
            $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
            $fileStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
            $fileEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
            $typeLabel = $type === 'pcs' ? 'Pieces' : 'Rupiah';

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
                    <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
                    <div class="period">Tipe ' . htmlspecialchars($typeLabel) . '</div>
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

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            $filename = 'Penjualan_Per_Item_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError('SalesItemController', 'exportPdf', $e, [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'type' => $request->get('type')
            ]);

            return response()->json(['error' => 'Failed to export PDF file'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NationalRevenueController extends Controller
{

    public function data(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $organization = $request->input('organization', '%');
        $type = $request->input('type', 'BRUTO');

        if ($type === 'NETTO') {
            // Netto query - includes returns (CNC documents) as negative values
            $queryResult = DB::select("
                SELECT
                    org.name AS branch_name,
                    COALESCE(SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
                    END), 0) AS total_revenue
                FROM
                    c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                WHERE
                    inv.ad_client_id = 1000001
                    AND inv.issotrx = 'Y'
                    AND invl.qtyinvoiced > 0
                    AND invl.linenetamt > 0
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name LIKE ?
                    AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
                    AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
                GROUP BY
                    org.name
                ORDER BY
                    total_revenue DESC
            ", [$organization, $startDate, $endDate]);

            // Convert stdClass to array format for ChartHelper
            $queryResult = collect($queryResult)->map(function ($item) {
                return (object) [
                    'branch_name' => $item->branch_name,
                    'total_revenue' => $item->total_revenue
                ];
            });
        } else {
            // Bruto query - original query (only INC documents)
            $queryResult = DB::table('c_invoice as inv')
                ->join('c_invoiceline as invl', 'inv.c_invoice_id', '=', 'invl.c_invoice_id')
                ->join('ad_org as org', 'inv.ad_org_id', '=', 'org.ad_org_id')
                ->select('org.name as branch_name', DB::raw('SUM(invl.linenetamt) as total_revenue'))
                ->where('inv.ad_client_id', 1000001)
                ->where('inv.issotrx', 'Y')
                ->where('invl.qtyinvoiced', '>', 0)
                ->where('invl.linenetamt', '>', 0)
                ->whereIn('inv.docstatus', ['CO', 'CL'])
                ->where('inv.isactive', 'Y')
                ->where('org.name', 'like', $organization)
                ->whereBetween(DB::raw('DATE(inv.dateinvoiced)'), [$startDate, $endDate])
                ->where('inv.documentno', 'like', 'INC%')
                ->groupBy('org.name')
                ->orderBy('total_revenue', 'desc')
                ->get();
        }

        $formattedData = ChartHelper::formatNationalRevenueData($queryResult);

        return response()->json($formattedData);
    }

    public function exportExcel(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $organization = $request->input('organization', '%');
        $type = $request->input('type', 'BRUTO');

        // Get the same data as the chart
        if ($type === 'NETTO') {
            // Netto query - includes returns (CNC documents) as negative values
            $queryResult = DB::select("
                SELECT
                    org.name AS branch_name,
                    COALESCE(SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
                    END), 0) AS total_revenue
                FROM
                    c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                WHERE
                    inv.ad_client_id = 1000001
                    AND inv.issotrx = 'Y'
                    AND invl.qtyinvoiced > 0
                    AND invl.linenetamt > 0
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name LIKE ?
                    AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
                    AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
                GROUP BY
                    org.name
                ORDER BY
                    total_revenue DESC
            ", [$organization, $startDate, $endDate]);

            // Convert stdClass to array format
            $queryResult = collect($queryResult)->map(function ($item) {
                return (object) [
                    'branch_name' => $item->branch_name,
                    'total_revenue' => $item->total_revenue
                ];
            });
        } else {
            // Bruto query - original query (only INC documents)
            $queryResult = DB::table('c_invoice as inv')
                ->join('c_invoiceline as invl', 'inv.c_invoice_id', '=', 'invl.c_invoice_id')
                ->join('ad_org as org', 'inv.ad_org_id', '=', 'org.ad_org_id')
                ->select('org.name as branch_name', DB::raw('SUM(invl.linenetamt) as total_revenue'))
                ->where('inv.ad_client_id', 1000001)
                ->where('inv.issotrx', 'Y')
                ->where('invl.qtyinvoiced', '>', 0)
                ->where('invl.linenetamt', '>', 0)
                ->whereIn('inv.docstatus', ['CO', 'CL'])
                ->where('inv.isactive', 'Y')
                ->where('org.name', 'like', $organization)
                ->whereBetween(DB::raw('DATE(inv.dateinvoiced)'), [$startDate, $endDate])
                ->where('inv.documentno', 'like', 'INC%')
                ->groupBy('org.name')
                ->orderBy('total_revenue', 'desc')
                ->get();
        }

        // Calculate total
        $totalRevenue = $queryResult->sum('total_revenue');

        // Format dates for filename and display
        $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
        $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
        $fileStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
        $fileEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
        $typeLabel = $type === 'NETTO' ? 'Netto' : 'Bruto';
        $filename = 'Penjualan_Nasional_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.xls';

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
                            <x:Name>Penjualan Nasional</x:Name>
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
                body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
                table { border-collapse: collapse; }
                th, td {
                    border: 1px solid #ddd;
                    padding: 4px 8px;
                    text-align: left;
                    font-size: 10pt;
                    white-space: nowrap;
                }
                th {
                    background-color: #4CAF50;
                    color: white;
                    font-weight: bold;
                    font-size: 10pt;
                }
                .title { font-size: 10pt; font-weight: bold; margin-bottom: 5px; }
                .period { font-size: 10pt; margin-bottom: 10px; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
                .number { text-align: right; }
                .col-no { width: 70px; }
                .col-branch { width: 250px; }
                .col-code { width: 160px; }
                .col-revenue { width: 280px; }
            </style>
        </head>
        <body>
            <div class="title">Penjualan Nasional</div>
            <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
            <div class="period">Tipe ' . $typeLabel . '</div>
            <table>
                <colgroup>
                    <col class="col-no">
                    <col class="col-branch">
                    <col class="col-code">
                    <col class="col-revenue">
                </colgroup>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Cabang</th>
                        <th>Kode Cabang</th>
                        <th style="text-align: right;">Penjualan (Rp)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . number_format($row->total_revenue, 2, '.', ',') . '</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalRevenue, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $organization = $request->input('organization', '%');
        $type = $request->input('type', 'BRUTO');

        // Get the same data as the chart
        if ($type === 'NETTO') {
            // Netto query - includes returns (CNC documents) as negative values
            $queryResult = DB::select("
                SELECT
                    org.name AS branch_name,
                    COALESCE(SUM(CASE
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                        WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
                    END), 0) AS total_revenue
                FROM
                    c_invoice inv
                    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                WHERE
                    inv.ad_client_id = 1000001
                    AND inv.issotrx = 'Y'
                    AND invl.qtyinvoiced > 0
                    AND invl.linenetamt > 0
                    AND inv.docstatus IN ('CO', 'CL')
                    AND inv.isactive = 'Y'
                    AND org.name LIKE ?
                    AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
                    AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
                GROUP BY
                    org.name
                ORDER BY
                    total_revenue DESC
            ", [$organization, $startDate, $endDate]);

            // Convert stdClass to array format
            $queryResult = collect($queryResult)->map(function ($item) {
                return (object) [
                    'branch_name' => $item->branch_name,
                    'total_revenue' => $item->total_revenue
                ];
            });
        } else {
            // Bruto query - original query (only INC documents)
            $queryResult = DB::table('c_invoice as inv')
                ->join('c_invoiceline as invl', 'inv.c_invoice_id', '=', 'invl.c_invoice_id')
                ->join('ad_org as org', 'inv.ad_org_id', '=', 'org.ad_org_id')
                ->select('org.name as branch_name', DB::raw('SUM(invl.linenetamt) as total_revenue'))
                ->where('inv.ad_client_id', 1000001)
                ->where('inv.issotrx', 'Y')
                ->where('invl.qtyinvoiced', '>', 0)
                ->where('invl.linenetamt', '>', 0)
                ->whereIn('inv.docstatus', ['CO', 'CL'])
                ->where('inv.isactive', 'Y')
                ->where('org.name', 'like', $organization)
                ->whereBetween(DB::raw('DATE(inv.dateinvoiced)'), [$startDate, $endDate])
                ->where('inv.documentno', 'like', 'INC%')
                ->groupBy('org.name')
                ->orderBy('total_revenue', 'desc')
                ->get();
        }

        // Calculate total
        $totalRevenue = $queryResult->sum('total_revenue');

        // Format dates for filename and display
        $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
        $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
        $fileStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
        $fileEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
        $typeLabel = $type === 'NETTO' ? 'Netto' : 'Bruto';

        // Create HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 20px; }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10pt;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .title {
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .period {
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
                    padding: 8px;
                    text-align: left;
                    font-size: 10pt;
                }
                th {
                    background-color: rgba(38, 102, 241, 0.9);
                    color: white;
                    font-weight: bold;
                }
                .number { text-align: right; }
                .total-row {
                    font-weight: bold;
                    background-color: #f2f2f2;
                }
                .total-row td {
                    border-top: 2px solid #333;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Penjualan Nasional</div>
                <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
                <div class="period">Tipe ' . $typeLabel . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 200px;">Nama Cabang</th>
                        <th style="width: 100px;">Kode Cabang</th>
                        <th style="width: 200px; text-align: right;">Penjualan (Rp)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . number_format($row->total_revenue, 2, '.', ',') . '</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalRevenue, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Penjualan_Nasional_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.pdf';

        return $pdf->download($filename);
    }
}

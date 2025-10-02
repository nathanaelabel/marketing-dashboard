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
            ->groupBy('org.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $formattedData = ChartHelper::formatNationalRevenueData($queryResult);

        return response()->json($formattedData);
    }

    public function exportExcel(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $organization = $request->input('organization', '%');

        // Get the same data as the chart
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
            ->groupBy('org.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Calculate total
        $totalRevenue = $queryResult->sum('total_revenue');

        // Format dates for filename and display
        $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d F Y');
        $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d F Y');
        $fileStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
        $fileEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
        $filename = 'National_Revenue_' . $fileStartDate . '_to_' . $fileEndDate . '.xls';

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
                            <x:Name>National Revenue</x:Name>
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
            <div class="title">National Revenue Report</div>
            <div class="period">Period: ' . $formattedStartDate . ' to ' . $formattedEndDate . '</div>
            <br>
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
                        <th>Branch Name</th>
                        <th>Branch Code</th>
                        <th style="text-align: right;">Revenue (Rp)</th>
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
}

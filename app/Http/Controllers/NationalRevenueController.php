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
        $formattedStartDate = \Carbon\Carbon::parse($startDate)->format('d-m-Y');
        $formattedEndDate = \Carbon\Carbon::parse($endDate)->format('d-m-Y');
        $filename = 'National_Revenue_' . $formattedStartDate . '_to_' . $formattedEndDate . '.csv';

        // Create CSV content
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($queryResult, $totalRevenue, $startDate, $endDate, $formattedStartDate, $formattedEndDate) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel encoding
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Add title and date range
            fputcsv($file, ['National Revenue Report']);
            fputcsv($file, ['Period: ' . $formattedStartDate . ' to ' . $formattedEndDate]);
            fputcsv($file, []); // Empty row

            // Add headers
            fputcsv($file, ['No', 'Branch Name', 'Branch Code', 'Revenue (Rp)']);

            // Add data rows
            $no = 1;
            foreach ($queryResult as $row) {
                fputcsv($file, [
                    $no++,
                    $row->branch_name,
                    ChartHelper::getBranchAbbreviation($row->branch_name),
                    number_format($row->total_revenue, 2, '.', '')
                ]);
            }

            // Add empty row before total
            fputcsv($file, []);

            // Add total row
            fputcsv($file, ['', '', 'TOTAL', number_format($totalRevenue, 2, '.', '')]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

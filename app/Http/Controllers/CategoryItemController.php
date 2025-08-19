<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategoryItemController extends Controller
{
    public function getData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $page = (int)$request->input('page', 1);
        $perPage = 8;

        // Base query to get all branches with invoices in the date range for pagination
        $baseQuery = DB::table('c_invoice as h')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate])
            ->select('org.name as branch')
            ->groupBy('org.name')
            ->orderBy('org.name');

        $allBranches = $baseQuery->get()->pluck('branch');
        $paginatedBranches = $allBranches->slice(($page - 1) * $perPage, $perPage)->values();

        if ($paginatedBranches->isEmpty()) {
            return response()->json(['labels' => [], 'datasets' => [], 'pagination' => ['currentPage' => $page, 'hasMorePages' => false]]);
        }

        // Main data query based on the provided SQL
        $dataQuery = DB::table('c_invoiceline as d')
            ->join('c_invoice as h', 'd.c_invoice_id', '=', 'h.c_invoice_id')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->join('m_product as prd', 'd.m_product_id', '=', 'prd.m_product_id')
            ->join('m_product_category as cat', 'prd.m_product_category_id', '=', 'cat.m_product_category_id')
            ->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(d.linenetamt) as total_revenue')
            )
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->where('d.qtyinvoiced', '>', 0)
            ->where('d.linenetamt', '>', 0)
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate])
            ->whereIn('org.name', $paginatedBranches)
            ->groupBy('org.name', 'cat.name');

        $data = $dataQuery->get();

        $categories = $data->pluck('category')->unique()->sort()->values();
        $dataByBranch = $data->groupBy('branch');

        $branchTotals = $paginatedBranches->mapWithKeys(function ($branch) use ($dataByBranch) {
            return [$branch => $dataByBranch->get($branch, collect())->sum('total_revenue')];
        });

        $totalRevenueForPage = $branchTotals->sum();

        $categoryColors = [
            'MIKA' => '#6bbb8b',
            'SPARE PART' => '#ea7f7f',
            'AKSESORIS' => '#8282ea',
            'CAT' => '#eebc6d',
            'PRODUCT IMPORT' => '#cb84cb',
        ];

        $datasets = $categories->map(function ($category) use ($paginatedBranches, $dataByBranch, $branchTotals, $totalRevenueForPage, $categoryColors) {
            $dataPoints = $paginatedBranches->map(function ($branch) use ($category, $dataByBranch, $branchTotals, $totalRevenueForPage) {
                $branchData = $dataByBranch->get($branch, collect());
                $revenue = $branchData->where('category', $category)->sum('total_revenue');
                $totalForBranch = $branchTotals->get($branch, 0);
                return [
                    'x' => $branch,
                    'y' => $totalForBranch > 0 ? ($revenue / $totalForBranch) : 0,
                    'v' => $revenue,
                    'value' => $totalForBranch,
                    'width' => $totalRevenueForPage > 0 ? ($totalForBranch / $totalRevenueForPage) : 0
                ];
            });

            return [
                'label' => $category,
                'data' => $dataPoints,
                'backgroundColor' => $categoryColors[$category] ?? '#c9cbcf',
            ];
        });

        return response()->json([
            'chartData' => [
                'labels' => $paginatedBranches,
                'datasets' => $datasets,
            ],
            'pagination' => [
                'currentPage' => $page,
                'hasMorePages' => $allBranches->count() > ($page * $perPage)
            ]
        ]);
    }
}

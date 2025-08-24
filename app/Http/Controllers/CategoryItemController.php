<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
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
        $perPage = $page === 1 ? 9 : 8;

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

        // Calculate offset for pagination with different page sizes
        $offset = $page === 1 ? 0 : 9 + (($page - 2) * 8);
        $paginatedBranches = $allBranches->slice($offset, $perPage)->values();

        if ($paginatedBranches->isEmpty()) {
            return response()->json([
                'chartData' => [
                    'labels' => [],
                    'datasets' => []
                ],
                'pagination' => [
                    'currentPage' => $page,
                    'hasMorePages' => false
                ]
            ]);
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

        // Enforce legend order
        $desiredOrder = ['MIKA', 'SPARE PART', 'CAT', 'PRODUCT IMPORT', 'AKSESORIS'];
        $foundCategories = $data->pluck('category')->unique()->values();
        // Keep only categories present in data, in the desired order
        $categories = collect($desiredOrder)
            ->filter(function ($c) use ($foundCategories) {
                return $foundCategories->contains($c);
            })
            ->values();
        $dataByBranch = $data->groupBy('branch');

        $branchTotals = $paginatedBranches->mapWithKeys(function ($branch) use ($dataByBranch) {
            return [$branch => $dataByBranch->get($branch, collect())->sum('total_revenue')];
        });

        $totalRevenueForPage = $branchTotals->sum();

        $categoryColors = ChartHelper::getCategoryColors();

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


        // Map branch names to abbreviations
        $abbreviatedLabels = $paginatedBranches->map(function ($branch) {
            return ChartHelper::getBranchAbbreviation($branch);
        })->toArray();

        // Calculate hasMorePages based on new pagination logic
        $totalProcessed = $page === 1 ? 9 : 9 + (($page - 1) * 8);
        $hasMorePages = $allBranches->count() > $totalProcessed;

        return response()->json([
            'chartData' => [
                'labels' => $abbreviatedLabels,
                'datasets' => $datasets->toArray()
            ],
            'pagination' => [
                'currentPage' => $page,
                'hasMorePages' => $hasMorePages
            ]
        ]);
    }
}

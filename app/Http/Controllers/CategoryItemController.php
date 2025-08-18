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
                'cat.name as category', // Using cat.name to align with previous logic
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

        $branchTotals = $data->groupBy('branch')->map(fn($group) => $group->sum('total_revenue'));

        $categories = $data->pluck('category')->unique()->sort()->values();

        $categoryColors = [
            'MIKA' => 'rgba(107, 187, 139, 0.9)',
            'SPARE PART' => 'rgba(234, 127, 127, 0.9)',
            'AKSESORIS' => 'rgba(130, 130, 234, 0.9)',
            'CAT' => 'rgba(238, 188, 109, 0.9)',
            'PRODUCT IMPORT' => 'rgba(203, 132, 203, 0.9)',
        ];

        $datasets = [];
        foreach ($categories as $category) {
            $treeData = [];
            foreach ($paginatedBranches as $branch) {
                if (!isset($branchTotals[$branch]) || $branchTotals[$branch] == 0) continue;
                $revenue = $data->where('branch', $branch)->where('category', $category)->sum('total_revenue');
                $treeData[] = [
                    'x' => $branch, // Branch name
                    'y' => $revenue, // Revenue for this category
                    'v' => $branchTotals[$branch] // Total revenue for the branch
                ];
            }

            $datasets[] = [
                'label' => $category,
                'tree' => $treeData,
                'backgroundColor' => $categoryColors[$category] ?? 'rgba(201, 203, 207, 0.8)',
            ];
        }

        return response()->json([
            'labels' => $paginatedBranches,
            'datasets' => $datasets,
            'pagination' => [
                'currentPage' => $page,
                'hasMorePages' => $allBranches->count() > ($page * $perPage)
            ]
        ]);
    }
}

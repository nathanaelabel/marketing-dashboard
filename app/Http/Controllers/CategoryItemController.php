<?php

namespace App\Http\Controllers;

use App\Models\CAllocationhdr;
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

        $baseQuery = CAllocationhdr::select('ad_org.name as branch')
            ->join('ad_org', 'c_allocationhdr.ad_org_id', '=', 'ad_org.ad_org_id')
            ->whereBetween('c_allocationhdr.datetrx', [$startDate, $endDate])
            ->groupBy('ad_org.name')
            ->orderBy('ad_org.name');

        $allBranches = $baseQuery->get()->pluck('branch');
        $paginatedBranches = $allBranches->slice(($page - 1) * $perPage, $perPage)->values();

        if ($paginatedBranches->isEmpty()) {
            return response()->json(['labels' => [], 'datasets' => [], 'pagination' => ['currentPage' => $page, 'hasMorePages' => false]]);
        }

        $dataQuery = CAllocationhdr::select(
            'ad_org.name as branch',
            'm_product_category.name as category',
            DB::raw('SUM(c_allocationline.amount) as total_revenue')
        )
            ->join('c_allocationline', 'c_allocationhdr.c_allocationhdr_id', '=', 'c_allocationline.c_allocationhdr_id')
            ->join('ad_org', 'c_allocationhdr.ad_org_id', '=', 'ad_org.ad_org_id')
            ->join('c_invoice', 'c_allocationline.c_invoice_id', '=', 'c_invoice.c_invoice_id')
            ->join('c_invoiceline', 'c_invoice.c_invoice_id', '=', 'c_invoiceline.c_invoice_id')
            ->join('m_product', 'c_invoiceline.m_product_id', '=', 'm_product.m_product_id')
            ->join('m_product_category', 'm_product.m_product_category_id', '=', 'm_product_category.m_product_category_id')
            ->whereBetween('c_allocationhdr.datetrx', [$startDate, $endDate])
            ->whereIn('ad_org.name', $paginatedBranches)
            ->groupBy('ad_org.name', 'm_product_category.name');

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

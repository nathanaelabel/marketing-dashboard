<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MProductCategory;
use App\Helpers\ChartHelper;

class NationalYearlyController extends Controller
{
    public function getData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $previousYear = $year - 1;
        $category = $request->get('category', 'MIKA');

        // Get current year data
        $currentYearData = $this->getRevenueData($year, $category);

        // Get previous year data
        $previousYearData = $this->getRevenueData($previousYear, $category);

        // Combine and format data using ChartHelper
        $formattedData = $this->formatYearlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

        return response()->json($formattedData);
    }

    public function getCategories()
    {
        $categories = MProductCategory::where('isactive', 'Y')
            ->whereIn('name', ['MIKA', 'SPARE PART'])
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    private function getRevenueData($year, $category)
    {
        // First get the category filter condition
        $categoryCondition = '';
        if ($category) {
            $categoryCondition = "AND EXISTS (
                SELECT 1 FROM m_product p 
                INNER JOIN m_product_category pc ON p.m_product_category_id = pc.m_product_category_id 
                WHERE p.m_product_id = invl.m_product_id 
                AND pc.name = :category
            )";
        }

        $query = "
            SELECT
                org.name AS branch_name, 
                COALESCE(SUM(invl.linenetamt), 0) AS total_revenue
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
                AND org.name NOT LIKE '%HEAD OFFICE%'
                AND EXTRACT(year FROM inv.dateinvoiced) = :year
                AND inv.documentno LIKE 'INC%'
                {$categoryCondition}
            GROUP BY
                org.name
            ORDER BY
                org.name
        ";

        $params = ['year' => $year];
        if ($category) {
            $params['category'] = $category;
        }

        return DB::select($query, $params);
    }

    private function formatYearlyComparisonData($currentYearData, $previousYearData, $year, $previousYear)
    {
        // Get all unique branches from both datasets
        $allBranches = collect($currentYearData)->pluck('branch_name')
            ->merge(collect($previousYearData)->pluck('branch_name'))
            ->unique()
            ->values();

        // Map data for each year
        $currentYearMap = collect($currentYearData)->keyBy('branch_name');
        $previousYearMap = collect($previousYearData)->keyBy('branch_name');

        $currentYearValues = [];
        $previousYearValues = [];

        foreach ($allBranches as $branch) {
            $currentRevenue = $currentYearMap->get($branch);
            $previousRevenue = $previousYearMap->get($branch);

            $currentYearValues[] = $currentRevenue ? $currentRevenue->total_revenue : 0;
            $previousYearValues[] = $previousRevenue ? $previousRevenue->total_revenue : 0;
        }

        // Get max value for Y-axis scaling
        $maxValue = max(max($currentYearValues), max($previousYearValues));

        // Use ChartHelper for Y-axis configuration
        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, array_merge($currentYearValues, $previousYearValues));
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

        // Get branch abbreviations
        $labels = $allBranches->map(function ($name) {
            return ChartHelper::getBranchAbbreviation($name);
        });

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $previousYear,
                    'data' => $previousYearValues,
                    // Blue 500 (lighter) for previous year
                    'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => $year,
                    'data' => $currentYearValues,
                    // Blue 600 (darker) for current year
                    'backgroundColor' => 'rgba(38, 102, 241, 0.9)',
                    'borderColor' => 'rgba(37, 99, 235, 1)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ]
            ],
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMax,
        ];
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MProductCategory;
use App\Helpers\ChartHelper;

class MonthlyBranchController extends Controller
{
    public function getData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $previousYear = $year - 1;
        $category = $request->get('category', 'MIKA');
        $branch = $request->get('branch', 'National');

        // Get current year data for all 12 months
        $currentYearData = $this->getMonthlyRevenueData($year, $category, $branch);

        // Get previous year data for all 12 months
        $previousYearData = $this->getMonthlyRevenueData($previousYear, $category, $branch);

        // Format data for chart
        $formattedData = $this->formatMonthlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

        return response()->json($formattedData);
    }

    public function getCategories()
    {
        $categories = ChartHelper::getCategories();
        return response()->json($categories);
    }

    public function getBranches()
    {
        try {
            $branches = ChartHelper::getLocations();

            // Add National option at the beginning
            $branchOptions = collect([
                [
                    'value' => 'National',
                    'display' => 'National'
                ]
            ]);

            // Map branches to include both value (full name) and display name
            $individualBranches = $branches->map(function ($branch) {
                return [
                    'value' => $branch,
                    'display' => ChartHelper::getBranchDisplayName($branch)
                ];
            });

            // Merge National option with branch options
            $allOptions = $branchOptions->merge($individualBranches);

            return response()->json($allOptions);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getMonthlyRevenueData($year, $category, $branch)
    {
        // Category filter condition
        $categoryCondition = '';
        if ($category) {
            $categoryCondition = "AND EXISTS (
                SELECT 1 FROM m_product p 
                INNER JOIN m_product_category pc ON p.m_product_category_id = pc.m_product_category_id 
                WHERE p.m_product_id = invl.m_product_id 
                AND pc.name = :category
            )";
        }

        // Branch condition - if National, sum all branches, otherwise filter by specific branch
        $branchCondition = '';
        if ($branch !== 'National') {
            $branchCondition = 'AND org.name = :branch';
        }

        $query = "
            SELECT
                EXTRACT(month FROM inv.dateinvoiced) AS month_number,
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
                {$branchCondition}
                AND EXTRACT(year FROM inv.dateinvoiced) = :year
                AND inv.documentno LIKE 'INC%'
                {$categoryCondition}
            GROUP BY
                EXTRACT(month FROM inv.dateinvoiced)
            ORDER BY
                month_number
        ";

        $params = ['year' => $year];
        if ($branch !== 'National') {
            $params['branch'] = $branch;
        }
        if ($category) {
            $params['category'] = $category;
        }

        return DB::select($query, $params);
    }

    private function formatMonthlyComparisonData($currentYearData, $previousYearData, $year, $previousYear)
    {
        // Create month labels
        $monthLabels = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December'
        ];

        // Map data by month (1-12)
        $currentYearMap = collect($currentYearData)->keyBy('month_number');
        $previousYearMap = collect($previousYearData)->keyBy('month_number');

        $currentYearValues = [];
        $previousYearValues = [];

        // Fill data for all 12 months
        for ($month = 1; $month <= 12; $month++) {
            $currentRevenue = $currentYearMap->get($month);
            $previousRevenue = $previousYearMap->get($month);

            $currentYearValues[] = $currentRevenue ? $currentRevenue->total_revenue : 0;
            $previousYearValues[] = $previousRevenue ? $previousRevenue->total_revenue : 0;
        }

        // Get max value for Y-axis scaling
        $maxValue = max(max($currentYearValues), max($previousYearValues));

        // Use ChartHelper for Y-axis configuration
        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, array_merge($currentYearValues, $previousYearValues));
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

        // Get datasets using ChartHelper
        $datasets = ChartHelper::getYearlyComparisonDatasets($year, $previousYear, $currentYearValues, $previousYearValues);

        return [
            'labels' => $monthLabels,
            'datasets' => $datasets,
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMax,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MProductCategory;
use App\Helpers\ChartHelper;

class MonthlyBranchController extends Controller
{
    public function getData(Request $request)
    {
        try {
            // Handle both year parameters (from frontend) and date parameters (legacy)
            $year = $request->input('year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Convert year to date range if year parameter is provided
            if ($year) {
                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';

                // If it's the current year, limit end date to today to avoid querying future dates
                $currentYear = date('Y');
                if ($year == $currentYear) {
                    $today = date('Y-m-d');
                    $endDate = min($endDate, $today);
                }
            } else {
                // Fallback to date parameters or defaults
                $startDate = $startDate ?: date('Y') . '-01-01';
                $endDate = $endDate ?: date('Y-m-d'); // Use today instead of end of year
                $year = date('Y', strtotime($startDate));
            }

            $previousYear = $year - 1;
            $category = $request->get('category', 'MIKA');
            $branch = $request->get('branch', 'National');

            // Get current year data using date range
            $currentYearData = $this->getMonthlyRevenueData($startDate, $endDate, $category, $branch);

            // Get previous year data using date range
            $previousStartDate = $previousYear . '-01-01';
            $previousEndDate = $previousYear . '-12-31';
            $previousYearData = $this->getMonthlyRevenueData($previousStartDate, $previousEndDate, $category, $branch);

            // Format data for chart
            $formattedData = $this->formatMonthlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

            return response()->json($formattedData);
        } catch (\Exception $e) {
            Log::error('MonthlyBranchController getData error: ' . $e->getMessage(), [
                'year' => $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'category' => $request->get('category'),
                'branch' => $request->get('branch'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch monthly branch data'], 500);
        }
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

    private function getMonthlyRevenueData($startDate, $endDate, $category, $branch)
    {
        // Branch condition - if National, sum all branches, otherwise filter by specific branch
        $branchCondition = '';
        $bindings = [];

        if ($branch !== 'National') {
            $branchCondition = 'AND org.name = ?';
            $bindings = [$branch, $startDate, $endDate, $category];
        } else {
            $bindings = [$startDate, $endDate, $category];
        }

        // Optimized query with direct JOIN instead of EXISTS subquery for better performance
        $query = "
            SELECT
                EXTRACT(month FROM inv.dateinvoiced) AS month_number,
                COALESCE(SUM(invl.linenetamt), 0) AS total_revenue
            FROM
                c_invoice inv
                INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
                INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
                INNER JOIN m_product p ON invl.m_product_id = p.m_product_id
                INNER JOIN m_product_category pc ON p.m_product_category_id = pc.m_product_category_id
            WHERE
                inv.ad_client_id = 1000001
                AND inv.issotrx = 'Y'
                AND invl.qtyinvoiced > 0
                AND invl.linenetamt > 0
                AND inv.docstatus IN ('CO', 'CL')
                AND inv.isactive = 'Y'
                {$branchCondition}
                AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
                AND inv.documentno LIKE 'INC%'
                AND pc.name = ?
            GROUP BY
                EXTRACT(month FROM inv.dateinvoiced)
            ORDER BY
                month_number
        ";

        return DB::select($query, $bindings);
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

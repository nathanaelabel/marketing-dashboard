<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChartHelper;

class NationalYearlyController extends Controller
{
    public function getData(Request $request)
    {
        try {
            // Handle both year parameters (from frontend) and date parameters (legacy)
            $year = $request->input('year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $today = date('Y-m-d'); // Get today's date for fair comparison
            $currentYear = date('Y');

            // Convert year to date range if year parameter is provided
            if ($year) {
                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';

                // If it's the current year, limit end date to today to avoid querying future dates
                if ($year == $currentYear) {
                    $endDate = $today;
                }
            } else {
                // Fallback to date parameters or defaults
                $startDate = $startDate ?: date('Y') . '-01-01';
                $endDate = $endDate ?: $today; // Use today instead of end of year
                $year = date('Y', strtotime($startDate));
            }

            $previousYear = $year - 1;
            $category = $request->get('category', 'MIKA');

            // Calculate fair comparison date ranges using ChartHelper
            // This ensures both years use the same period length (e.g., Jan-Sep for both 2024 and 2025)
            // instead of comparing full year 2024 vs partial year 2025
            $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);

            // Get current year data using calculated date range
            $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category);

            // Get previous year data using equivalent date range (same period for fair comparison)
            $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category);

            // Combine and format data using ChartHelper
            $formattedData = $this->formatYearlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

            return response()->json($formattedData);
        } catch (\Exception $e) {
            Log::error('NationalYearlyController getData error: ' . $e->getMessage(), [
                'year' => $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'date_ranges' => $dateRanges ?? null,
                'category' => $request->get('category'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch national yearly data'], 500);
        }
    }

    public function getCategories()
    {
        // Delegate to shared helper to avoid duplication and keep logic centralized
        $categories = ChartHelper::getCategories();
        return response()->json($categories);
    }

    private function getRevenueData($startDate, $endDate, $category)
    {
        // Optimized query with direct JOIN and date range filtering
        $query = "
            SELECT
                org.name AS branch_name, 
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
                AND org.name NOT LIKE '%HEAD OFFICE%'
                AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
                AND inv.documentno LIKE 'INC%'
                AND pc.name = ?
            GROUP BY
                org.name
            ORDER BY
                org.name
        ";

        return DB::select($query, [$startDate, $endDate, $category]);
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

        // Get datasets using ChartHelper
        $datasets = ChartHelper::getYearlyComparisonDatasets($year, $previousYear, $currentYearValues, $previousYearValues);

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMax,
        ];
    }
}

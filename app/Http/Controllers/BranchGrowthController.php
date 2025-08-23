<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ChartHelper;

class BranchGrowthController extends Controller
{
    public function getData(Request $request)
    {
        $startYear = $request->get('start_year', 2024);
        $endYear = $request->get('end_year', 2025);
        $category = $request->get('category', 'MIKA');
        $branch = $request->get('branch', 'National');

        // Get data for all years in the range
        $data = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearData = $this->getYearlyRevenueData($year, $category, $branch);
            $data[$year] = $yearData;
        }

        // Format data for line chart
        $formattedData = $this->formatGrowthData($data, $startYear, $endYear);

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

    private function getYearlyRevenueData($year, $category, $branch)
    {
        // Branch condition - if National, sum all branches, otherwise filter by specific branch
        $branchCondition = '';
        $branchBinding = [];
        if ($branch !== 'National') {
            $branchCondition = 'AND org.name = ?';
            $branchBinding[] = $branch;
        }

        // Optimized query using conditional aggregation instead of UNION ALL
        $query = "
            SELECT 
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 1 THEN d.linenetamt ELSE 0 END) AS b01,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 2 THEN d.linenetamt ELSE 0 END) AS b02,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 3 THEN d.linenetamt ELSE 0 END) AS b03,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 4 THEN d.linenetamt ELSE 0 END) AS b04,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 5 THEN d.linenetamt ELSE 0 END) AS b05,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 6 THEN d.linenetamt ELSE 0 END) AS b06,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 7 THEN d.linenetamt ELSE 0 END) AS b07,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 8 THEN d.linenetamt ELSE 0 END) AS b08,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 9 THEN d.linenetamt ELSE 0 END) AS b09,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 10 THEN d.linenetamt ELSE 0 END) AS b10,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 11 THEN d.linenetamt ELSE 0 END) AS b11,
                SUM(CASE WHEN EXTRACT(month FROM h.dateinvoiced) = 12 THEN d.linenetamt ELSE 0 END) AS b12
            FROM
                c_invoiceline d
                INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
            WHERE
                h.issotrx = 'Y'
                AND h.ad_client_id = 1000001
                AND d.qtyinvoiced > 0 
                AND d.linenetamt > 0
                AND h.docstatus IN ('CO', 'CL')
                AND h.documentno LIKE 'INC%'
                AND cat.name = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
                {$branchCondition}
        ";

        $bindings = array_merge([$category, $year], $branchBinding);
        $result = DB::select($query, $bindings);

        return $result[0] ?? (object)[
            'b01' => 0, 'b02' => 0, 'b03' => 0, 'b04' => 0,
            'b05' => 0, 'b06' => 0, 'b07' => 0, 'b08' => 0,
            'b09' => 0, 'b10' => 0, 'b11' => 0, 'b12' => 0
        ];
    }

    private function formatGrowthData($data, $startYear, $endYear)
    {
        $monthLabels = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        $datasets = [];
        $categoryColors = ChartHelper::getCategoryColors();
        $colorIndex = 0;
        $defaultColors = [
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(16, 185, 129, 0.8)',   // Green  
            'rgba(245, 158, 11, 0.8)',   // Yellow
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(139, 92, 246, 0.8)'    // Purple
        ];

        // Create dataset for each year
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearData = $data[$year];
            $monthlyValues = [
                $yearData->b01 ?? 0, $yearData->b02 ?? 0, $yearData->b03 ?? 0, $yearData->b04 ?? 0,
                $yearData->b05 ?? 0, $yearData->b06 ?? 0, $yearData->b07 ?? 0, $yearData->b08 ?? 0,
                $yearData->b09 ?? 0, $yearData->b10 ?? 0, $yearData->b11 ?? 0, $yearData->b12 ?? 0
            ];

            // Use default colors cycling through them
            $color = $defaultColors[$colorIndex % count($defaultColors)];
            
            $datasets[] = [
                'label' => (string)$year,
                'data' => $monthlyValues,
                'borderColor' => $color,
                'backgroundColor' => str_replace('0.8)', '0.1)', $color), // More transparent background
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.1
            ];
            
            $colorIndex++;
        }

        // Calculate max value for Y-axis scaling
        $allValues = [];
        foreach ($datasets as $dataset) {
            $allValues = array_merge($allValues, $dataset['data']);
        }
        $maxValue = max($allValues);

        // Use ChartHelper for Y-axis configuration
        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, $allValues);
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

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

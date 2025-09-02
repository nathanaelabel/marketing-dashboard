<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChartHelper;

class BranchGrowthController extends Controller
{
    public function getData(Request $request)
    {
        try {
            $startYear = $request->get('start_year', 2024);
            $endYear = $request->get('end_year', 2025);
            $category = $request->get('category', 'MIKA');
            $branch = $request->get('branch', 'National');

            // Validate year range
            if ($startYear > $endYear) {
                return response()->json(['error' => 'Start year cannot be greater than end year'], 400);
            }

            if ($endYear > 2025) {
                return response()->json(['error' => 'End year cannot be greater than 2025'], 400);
            }

            // Get data for all years in the range
            $data = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                $yearData = $this->getYearlyRevenueData($year, $category, $branch);
                $data[$year] = $yearData;
            }

            // Format data for line chart
            $formattedData = $this->formatGrowthData($data, $startYear, $endYear);

            return response()->json($formattedData);
        } catch (\Exception $e) {
            Log::error('BranchGrowthController getData error: ' . $e->getMessage(), [
                'start_year' => $request->get('start_year'),
                'end_year' => $request->get('end_year'),
                'category' => $request->get('category'),
                'branch' => $request->get('branch'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch branch growth data'], 500);
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

    private function getYearlyRevenueData($year, $category, $branch)
    {
        try {
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

            // Ensure we always return a valid object with all months initialized to 0
            $defaultData = (object)[
                'b01' => 0,
                'b02' => 0,
                'b03' => 0,
                'b04' => 0,
                'b05' => 0,
                'b06' => 0,
                'b07' => 0,
                'b08' => 0,
                'b09' => 0,
                'b10' => 0,
                'b11' => 0,
                'b12' => 0
            ];

            if (empty($result) || !isset($result[0])) {
                return $defaultData;
            }

            $data = $result[0];

            // Convert null values to 0 and ensure all months are present
            foreach ($defaultData as $month => $defaultValue) {
                if (!isset($data->$month) || $data->$month === null) {
                    $data->$month = 0;
                } else {
                    // Convert to float to ensure numeric type
                    $data->$month = (float)$data->$month;
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching yearly revenue data', [
                'year' => $year,
                'category' => $category,
                'branch' => $branch,
                'error' => $e->getMessage()
            ]);

            // Return default zero data on error
            return (object)[
                'b01' => 0,
                'b02' => 0,
                'b03' => 0,
                'b04' => 0,
                'b05' => 0,
                'b06' => 0,
                'b07' => 0,
                'b08' => 0,
                'b09' => 0,
                'b10' => 0,
                'b11' => 0,
                'b12' => 0
            ];
        }
    }

    private function formatGrowthData($data, $startYear, $endYear)
    {
        try {
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

            $datasets = [];
            $defaultColors = [
                'rgba(59, 130, 246, 0.8)',   // Blue
                'rgba(16, 185, 129, 0.8)',   // Green  
                'rgba(245, 158, 11, 0.8)',   // Yellow
                'rgba(239, 68, 68, 0.8)',    // Red
                'rgba(139, 92, 246, 0.8)'    // Purple
            ];

            $colorIndex = 0;
            $allValues = [];

            // Create dataset for each year
            for ($year = $startYear; $year <= $endYear; $year++) {
                $yearData = $data[$year] ?? null;

                if (!$yearData) {
                    continue; // Skip if no data for this year
                }

                $monthlyValues = [
                    (float)($yearData->b01 ?? 0),
                    (float)($yearData->b02 ?? 0),
                    (float)($yearData->b03 ?? 0),
                    (float)($yearData->b04 ?? 0),
                    (float)($yearData->b05 ?? 0),
                    (float)($yearData->b06 ?? 0),
                    (float)($yearData->b07 ?? 0),
                    (float)($yearData->b08 ?? 0),
                    (float)($yearData->b09 ?? 0),
                    (float)($yearData->b10 ?? 0),
                    (float)($yearData->b11 ?? 0),
                    (float)($yearData->b12 ?? 0)
                ];

                // Add to all values for max calculation
                $allValues = array_merge($allValues, $monthlyValues);

                // Use default colors cycling through them
                $color = $defaultColors[$colorIndex % count($defaultColors)];

                // Create formatted values for chart display
                $formattedValues = [];
                foreach ($monthlyValues as $value) {
                    $formattedValues[] = ChartHelper::formatNumberForDisplay($value, 1);
                }

                $datasets[] = [
                    'label' => (string)$year,
                    'data' => $monthlyValues,
                    'formattedData' => $formattedValues,
                    'borderColor' => $color,
                    'backgroundColor' => str_replace('0.8)', '0.1)', $color),
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.1
                ];

                $colorIndex++;
            }

            // Handle case where no datasets were created
            if (empty($datasets)) {
                return [
                    'labels' => $monthLabels,
                    'datasets' => [],
                    'yAxisLabel' => 'Revenue',
                    'yAxisDivisor' => 1,
                    'yAxisUnit' => '',
                    'suggestedMax' => 100,
                    'message' => 'No data available for the selected year range'
                ];
            }

            // Calculate max value for Y-axis scaling
            $maxValue = empty($allValues) ? 0 : max($allValues);

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
        } catch (\Exception $e) {
            Log::error('Error formatting growth data', [
                'start_year' => $startYear,
                'end_year' => $endYear,
                'error' => $e->getMessage()
            ]);

            // Return minimal valid structure on error
            return [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'datasets' => [],
                'yAxisLabel' => 'Revenue',
                'yAxisDivisor' => 1,
                'yAxisUnit' => '',
                'suggestedMax' => 100,
                'error' => 'Failed to format chart data'
            ];
        }
    }
}

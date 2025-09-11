<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChartHelper;

class TargetRevenueController extends Controller
{
    public function getData(Request $request)
    {
        try {
            $month = $request->get('month', date('n')); // Current month
            $year = $request->get('year', date('Y')); // Current year
            $category = $request->get('category', 'MIKA');

            // Check if targets exist for this month/year/category
            $targetsExist = DB::table('branch_targets')
                ->where('month', $month)
                ->where('year', $year)
                ->where('category', $category)
                ->exists();

            if (!$targetsExist) {
                return response()->json([
                    'no_targets' => true,
                    'message' => 'Target belum diinput untuk periode ini',
                    'month' => $month,
                    'year' => $year,
                    'category' => $category
                ]);
            }

            // Get actual revenue data using the provided query
            $actualData = $this->getActualRevenueData($month, $year, $category);

            // Get target data
            $targetData = $this->getTargetData($month, $year, $category);

            // Format data for horizontal bar chart
            $formattedData = $this->formatTargetRevenueData($actualData, $targetData);

            return response()->json($formattedData);
        } catch (\Exception $e) {
            Log::error('TargetRevenueController getData error: ' . $e->getMessage(), [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'category' => $request->get('category'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch target revenue data'], 500);
        }
    }

    public function getCategories()
    {
        $categories = ChartHelper::getCategories();
        return response()->json($categories);
    }

    private function getActualRevenueData($month, $year, $category)
    {
        try {
            $query = "
                SELECT
                    ss.cabang AS cabang, 
                    SUM(ss.total_bruto) AS total_bruto, 
                    SUM(ss.total_retur) AS total_retur, 
                    (SUM(ss.total_bruto) - SUM(ss.total_retur)) AS netto
                FROM
                (
                    --BRUTO
                    SELECT
                        org.name AS cabang, 
                        SUM(d.linenetamt) AS total_bruto, 
                        0 AS total_retur
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
                        AND h.docstatus in ('CO', 'CL')
                        AND h.documentno LIKE 'INC%'
                        AND cat.name = ?
                        AND EXTRACT(month FROM h.dateinvoiced) = ?
                        AND EXTRACT(year FROM h.dateinvoiced) = ?
                    GROUP BY
                        org.name

                    UNION ALL 

                    --RETUR
                    SELECT
                        org.name AS cabang, 
                        0 AS total_bruto, 
                        SUM(d.linenetamt) AS total_retur
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
                        AND h.docstatus in ('CO', 'CL')
                        AND h.documentno LIKE 'CNC%'
                        AND cat.name = ?
                        AND EXTRACT(month FROM h.dateinvoiced) = ?
                        AND EXTRACT(year FROM h.dateinvoiced) = ?
                    GROUP BY
                        org.name
                ) AS ss
                GROUP BY
                    ss.cabang
                ORDER BY
                    ss.cabang
            ";

            $results = DB::select($query, [$category, $month, $year, $category, $month, $year]);

            // Convert to associative array with branch name as key
            $data = [];
            foreach ($results as $result) {
                $data[$result->cabang] = [
                    'bruto' => (float)$result->total_bruto,
                    'retur' => (float)$result->total_retur,
                    'netto' => (float)$result->netto
                ];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching actual revenue data', [
                'month' => $month,
                'year' => $year,
                'category' => $category,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function getTargetData($month, $year, $category)
    {
        try {
            $targets = DB::table('branch_targets')
                ->where('month', $month)
                ->where('year', $year)
                ->where('category', $category)
                ->get();

            $data = [];
            foreach ($targets as $target) {
                $data[$target->branch_name] = (float)$target->target_amount;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching target data', [
                'month' => $month,
                'year' => $year,
                'category' => $category,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function formatTargetRevenueData($actualData, $targetData)
    {
        try {
            // Get all branch names from both actual and target data
            $allBranches = array_unique(array_merge(array_keys($actualData), array_keys($targetData)));
            sort($allBranches);

            $labels = [];
            $targetValues = [];
            $realizationValues = [];
            $percentages = [];

            foreach ($allBranches as $branchName) {
                $abbreviation = ChartHelper::getBranchAbbreviation($branchName);
                $labels[] = $abbreviation;

                $target = $targetData[$branchName] ?? 0;
                $actual = $actualData[$branchName]['netto'] ?? 0;

                $targetValues[] = $target;
                $realizationValues[] = $actual;

                // Calculate percentage
                $percentage = $target > 0 ? ($actual / $target) * 100 : 0;
                $percentages[] = round($percentage);
            }

            // Calculate max value for Y-axis scaling
            $allValues = array_merge($targetValues, $realizationValues);
            $maxValue = empty($allValues) ? 0 : max($allValues);

            // Use ChartHelper for Y-axis configuration
            $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, $allValues);
            $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Target',
                        'data' => $targetValues,
                        'backgroundColor' => 'rgba(229, 231, 235, 0.8)', // Light gray
                        'borderColor' => 'rgba(156, 163, 175, 1)',
                        'borderWidth' => 1
                    ],
                    [
                        'label' => 'Realization',
                        'data' => $realizationValues,
                        'backgroundColor' => 'rgba(251, 191, 36, 0.8)', // Orange/yellow
                        'borderColor' => 'rgba(245, 158, 11, 1)',
                        'borderWidth' => 1
                    ]
                ],
                'percentages' => $percentages,
                'yAxisLabel' => $yAxisConfig['label'],
                'yAxisDivisor' => $yAxisConfig['divisor'],
                'yAxisUnit' => $yAxisConfig['unit'],
                'suggestedMax' => $suggestedMax,
            ];
        } catch (\Exception $e) {
            Log::error('Error formatting target revenue data', [
                'error' => $e->getMessage()
            ]);

            return [
                'labels' => [],
                'datasets' => [],
                'percentages' => [],
                'yAxisLabel' => 'Revenue',
                'yAxisDivisor' => 1,
                'yAxisUnit' => '',
                'suggestedMax' => 100,
                'error' => 'Failed to format chart data'
            ];
        }
    }
}

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
            // Handle both year parameters (from frontend) and date parameters (legacy)
            $startYear = $request->input('start_year');
            $endYear = $request->input('end_year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Convert years to date ranges if year parameters are provided
            if ($startYear && $endYear) {
                $startDate = $startYear . '-01-01';
                $endDate = $endYear . '-12-31';
            } else {
                // Fallback to date parameters or defaults
                $startDate = $startDate ?: '2024-01-01';
                $endDate = $endDate ?: now()->toDateString();
            }

            $category = $request->get('category', 'MIKA');
            $branch = $request->get('branch', 'National');

            // Get revenue data using date range filtering like National Revenue
            $data = $this->getRevenueDataByDateRange($startDate, $endDate, $category, $branch);

            // Format data for line chart
            $formattedData = $this->formatMonthlyGrowthData($data, $startDate, $endDate);

            return response()->json($formattedData);
        } catch (\Exception $e) {
            Log::error('BranchGrowthController getData error: ' . $e->getMessage(), [
                'start_year' => $request->get('start_year'),
                'end_year' => $request->get('end_year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
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

    private function getRevenueDataByDateRange($startDate, $endDate, $category, $branch)
    {
        try {
            // Branch condition - if National, sum all branches, otherwise filter by specific branch
            $branchCondition = '';
            $branchBinding = [];
            if ($branch !== 'National') {
                $branchCondition = 'AND org.name = ?';
                $branchBinding[] = $branch;
            }

            $query = "
                SELECT
                    EXTRACT(year FROM h.dateinvoiced) as year,
                    EXTRACT(month FROM h.dateinvoiced) as month,
                    SUM(CASE 
                        WHEN h.documentno LIKE 'INC%' THEN d.linenetamt 
                        WHEN h.documentno LIKE 'CNC%' THEN -d.linenetamt 
                        ELSE 0 
                    END) as net_revenue
                FROM
                    c_invoiceline d
                    INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                WHERE
                    h.issotrx = 'Y'
                    AND h.ad_client_id = 1000001
                    AND h.docstatus IN ('CO', 'CL')
                    AND (h.documentno LIKE 'INC%' OR h.documentno LIKE 'CNC%')
                    AND cat.name = ?
                    AND DATE(h.dateinvoiced) BETWEEN ? AND ?
                    {$branchCondition}
                GROUP BY
                    EXTRACT(year FROM h.dateinvoiced),
                    EXTRACT(month FROM h.dateinvoiced)
                ORDER BY
                    year, month
            ";

            $bindings = array_merge([$category, $startDate, $endDate], $branchBinding);

            $result = DB::select($query, $bindings);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error fetching revenue data by date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'branch' => $branch,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function formatMonthlyGrowthData($data, $startDate, $endDate)
    {
        try {
            // Parse start and end dates to get year range
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $startYear = (int)$start->format('Y');
            $endYear = (int)$end->format('Y');

            // Month labels (Jan - Dec)
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

            // Group data by year
            $yearlyData = [];
            foreach ($data as $row) {
                $year = (int)$row->year;
                $month = (int)$row->month;
                if (!isset($yearlyData[$year])) {
                    $yearlyData[$year] = array_fill(1, 12, 0); // Initialize months 1-12 with 0
                }
                $yearlyData[$year][$month] = (float)$row->net_revenue;
            }

            // Create datasets for each year
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

            for ($year = $startYear; $year <= $endYear; $year++) {
                $monthlyValues = [];

                // Get data for each month (1-12)
                for ($month = 1; $month <= 12; $month++) {
                    $value = isset($yearlyData[$year][$month]) ? $yearlyData[$year][$month] : 0;
                    $monthlyValues[] = $value;
                    if ($value > 0) {
                        $allValues[] = $value;
                    }
                }

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

            // Calculate max value for Y-axis
            $maxValue = !empty($allValues) ? max($allValues) : 100;

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
            Log::error('Error formatting monthly growth data', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);

            return [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'datasets' => [
                    [
                        'label' => 'Net Revenue',
                        'data' => array_fill(0, 12, 0),
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'tension' => 0.1
                    ]
                ],
                'yAxisLabel' => 'Revenue',
                'yAxisDivisor' => 1,
                'yAxisUnit' => '',
                'suggestedMax' => 100,
                'error' => 'Failed to format chart data'
            ];
        }
    }

    private function getMultiYearRevenueData($startYear, $endYear, $category, $branch)
    {
        try {
            // Branch condition - if National, sum all branches, otherwise filter by specific branch
            $branchCondition = '';
            $branchBinding = [];
            if ($branch !== 'National') {
                $branchCondition = 'AND org.name = ?';
                $branchBinding[] = $branch;
            }

            // Build conditional aggregation for all years and months (BRUTO - RETUR)
            $yearSelects = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    $yearSelects[] = "SUM(CASE 
                        WHEN EXTRACT(year FROM h.dateinvoiced) = $year 
                        AND EXTRACT(month FROM h.dateinvoiced) = $month 
                        AND h.documentno LIKE 'INC%' 
                        THEN d.linenetamt 
                        WHEN EXTRACT(year FROM h.dateinvoiced) = $year 
                        AND EXTRACT(month FROM h.dateinvoiced) = $month 
                        AND h.documentno LIKE 'CNC%' 
                        THEN -d.linenetamt 
                        ELSE 0 
                    END) AS y{$year}_b" . str_pad($month, 2, '0', STR_PAD_LEFT);
                }
            }

            $selectClause = implode(",\n                    ", $yearSelects);

            $query = "
                SELECT 
                    $selectClause
                FROM
                    c_invoiceline d
                    INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                WHERE
                    h.issotrx = 'Y'
                    AND h.ad_client_id = 1000001
                    AND h.docstatus IN ('CO', 'CL')
                    AND (h.documentno LIKE 'INC%' OR h.documentno LIKE 'CNC%')
                    AND cat.name = ?
                    AND h.dateinvoiced >= ? AND h.dateinvoiced < ?
                    {$branchCondition}
            ";

            // Use date range instead of EXTRACT for better performance
            $startDate = $startYear . '-01-01';
            $endDate = ($endYear + 1) . '-01-01';

            $bindings = array_merge([$category, $startDate, $endDate], $branchBinding);

            Log::info('BranchGrowthController query debug', [
                'query' => $query,
                'bindings' => $bindings,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'category' => $category,
                'branch' => $branch
            ]);

            $result = DB::select($query, $bindings);

            Log::info('BranchGrowthController query result', [
                'result_count' => count($result),
                'result' => $result
            ]);

            // Convert result to year-based structure
            $data = [];
            if (!empty($result) && isset($result[0])) {
                $row = $result[0];
                for ($year = $startYear; $year <= $endYear; $year++) {
                    $yearData = (object)[
                        'b01' => (float)($row->{"y{$year}_b01"} ?? 0),
                        'b02' => (float)($row->{"y{$year}_b02"} ?? 0),
                        'b03' => (float)($row->{"y{$year}_b03"} ?? 0),
                        'b04' => (float)($row->{"y{$year}_b04"} ?? 0),
                        'b05' => (float)($row->{"y{$year}_b05"} ?? 0),
                        'b06' => (float)($row->{"y{$year}_b06"} ?? 0),
                        'b07' => (float)($row->{"y{$year}_b07"} ?? 0),
                        'b08' => (float)($row->{"y{$year}_b08"} ?? 0),
                        'b09' => (float)($row->{"y{$year}_b09"} ?? 0),
                        'b10' => (float)($row->{"y{$year}_b10"} ?? 0),
                        'b11' => (float)($row->{"y{$year}_b11"} ?? 0),
                        'b12' => (float)($row->{"y{$year}_b12"} ?? 0)
                    ];
                    $data[$year] = $yearData;
                }
            } else {
                // Return default zero data for all years
                for ($year = $startYear; $year <= $endYear; $year++) {
                    $data[$year] = (object)[
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

            return $data;
        } catch (\Exception $e) {
            Log::error('Error fetching multi-year revenue data', [
                'start_year' => $startYear,
                'end_year' => $endYear,
                'category' => $category,
                'branch' => $branch,
                'error' => $e->getMessage()
            ]);

            // Return default zero data for all years on error
            $data = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                $data[$year] = (object)[
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
            return $data;
        }
    }

    public function exportExcel(Request $request)
    {
        $startYear = $request->input('start_year', 2024);
        $endYear = $request->input('end_year', 2025);
        $category = $request->input('category', 'MIKA');
        $branch = $request->input('branch', 'National');

        $startDate = $startYear . '-01-01';
        $endDate = $endYear . '-12-31';

        // Get revenue data
        $data = $this->getRevenueDataByDateRange($startDate, $endDate, $category, $branch);

        $branchDisplay = $branch === 'National' ? 'National' : ChartHelper::getBranchDisplayName($branch);
        $filename = 'Branch_Revenue_Growth_' . $startYear . '-' . $endYear . '_' . str_replace(' ', '_', $branchDisplay) . '_' . str_replace(' ', '_', $category) . '.xls';

        // Create XLS content using HTML table format
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $monthLabels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        $html = '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Branch Revenue Growth</x:Name>
                            <x:WorksheetOptions>
                                <x:Print>
                                    <x:ValidPrinterInfo/>
                                </x:Print>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: rgba(38, 102, 241, 0.9); color: white; font-weight: bold; }
                .number { text-align: right; mso-number-format: "#,##0.00"; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
                .year-header { text-align: center; }
            </style>
        </head>
        <body>
            <h2>Branch Revenue Growth Report</h2>
            <p>Period: ' . $startYear . ' - ' . $endYear . ' | Branch: ' . htmlspecialchars($branchDisplay) . ' | Category: ' . htmlspecialchars($category) . '</p>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Month</th>';

        // Year headers
        for ($year = $startYear; $year <= $endYear; $year++) {
            $html .= '<th class="year-header">' . $year . '</th>';
        }

        $html .= '
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $yearTotals = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearTotals[$year] = 0;
        }

        // Month rows
        for ($month = 1; $month <= 12; $month++) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . $monthLabels[$month - 1] . '</td>';

            for ($year = $startYear; $year <= $endYear; $year++) {
                $monthKey = 'b' . str_pad($month, 2, '0', STR_PAD_LEFT);
                $value = isset($data[$year]) && isset($data[$year]->$monthKey) ? $data[$year]->$monthKey : 0;
                $yearTotals[$year] += $value;
                $html .= '<td class="number">' . number_format($value, 2, '.', ',') . '</td>';
            }

            $html .= '</tr>';
        }

        // Total row
        $html .= '
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL</strong></td>';

        for ($year = $startYear; $year <= $endYear; $year++) {
            $html .= '<td class="number"><strong>' . number_format($yearTotals[$year], 2, '.', ',') . '</strong></td>';
        }

        $html .= '
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $startYear = $request->input('start_year', 2024);
        $endYear = $request->input('end_year', 2025);
        $category = $request->input('category', 'MIKA');
        $branch = $request->input('branch', 'National');

        $startDate = $startYear . '-01-01';
        $endDate = $endYear . '-12-31';

        // Get revenue data
        $data = $this->getRevenueDataByDateRange($startDate, $endDate, $category, $branch);

        $branchDisplay = $branch === 'National' ? 'National' : ChartHelper::getBranchDisplayName($branch);

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

        // Create HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 20px; }
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 9pt;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .title { 
                    font-size: 16pt; 
                    font-weight: bold; 
                    margin-bottom: 5px;
                }
                .period { 
                    font-size: 10pt; 
                    color: #666;
                    margin-bottom: 20px;
                }
                table { 
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 6px; 
                    text-align: left;
                    font-size: 8pt;
                }
                th { 
                    background-color: rgba(38, 102, 241, 0.9); 
                    color: white; 
                    font-weight: bold;
                }
                .number { text-align: right; }
                .total-row { 
                    font-weight: bold; 
                    background-color: #f2f2f2;
                }
                .total-row td {
                    border-top: 2px solid #333;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Branch Revenue Growth Report</div>
                <div class="period">Period: ' . $startYear . ' - ' . $endYear . ' | Branch: ' . htmlspecialchars($branchDisplay) . ' | Category: ' . htmlspecialchars($category) . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 140px;">Month</th>';

        // Year headers
        $yearCount = $endYear - $startYear + 1;
        $colWidth = (100 - 50 - 140) / $yearCount;
        for ($year = $startYear; $year <= $endYear; $year++) {
            $html .= '<th style="width: ' . $colWidth . '%; text-align: right;">' . $year . '</th>';
        }

        $html .= '
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $yearTotals = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearTotals[$year] = 0;
        }

        // Month rows
        for ($month = 1; $month <= 12; $month++) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . $monthLabels[$month - 1] . '</td>';

            for ($year = $startYear; $year <= $endYear; $year++) {
                $monthKey = 'b' . str_pad($month, 2, '0', STR_PAD_LEFT);
                $value = isset($data[$year]) && isset($data[$year]->$monthKey) ? $data[$year]->$monthKey : 0;
                $yearTotals[$year] += $value;
                $html .= '<td class="number">' . number_format($value, 2, '.', ',') . '</td>';
            }

            $html .= '</tr>';
        }

        // Total row
        $html .= '
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL</strong></td>';

        for ($year = $startYear; $year <= $endYear; $year++) {
            $html .= '<td class="number"><strong>' . number_format($yearTotals[$year], 2, '.', ',') . '</strong></td>';
        }

        $html .= '
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Branch_Revenue_Growth_' . $startYear . '-' . $endYear . '_' . str_replace(' ', '_', $branchDisplay) . '_' . str_replace(' ', '_', $category) . '.pdf';

        return $pdf->download($filename);
    }
}

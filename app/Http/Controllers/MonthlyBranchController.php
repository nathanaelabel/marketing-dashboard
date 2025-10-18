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

    public function exportExcel(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $branch = $request->input('branch', 'National');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $today = date('Y-m-d');
            $endDate = min($endDate, $today);
        }

        $previousYear = $year - 1;

        // Get current year data
        $currentYearData = $this->getMonthlyRevenueData($startDate, $endDate, $category, $branch);

        // Get previous year data
        $previousStartDate = $previousYear . '-01-01';
        $previousEndDate = $previousYear . '-12-31';
        $previousYearData = $this->getMonthlyRevenueData($previousStartDate, $previousEndDate, $category, $branch);

        // Month labels
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

        // Map data by month
        $currentYearMap = collect($currentYearData)->keyBy('month_number');
        $previousYearMap = collect($previousYearData)->keyBy('month_number');

        $branchDisplay = $branch === 'National' ? 'National' : ChartHelper::getBranchDisplayName($branch);
        $filename = 'Monthly_Branch_Revenue_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $branchDisplay) . '_' . str_replace(' ', '_', $category) . '.xls';

        // Create XLS content using HTML table format
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $html = '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Monthly Branch Revenue</x:Name>
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
                body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
                table { border-collapse: collapse; }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 4px 8px; 
                    text-align: left; 
                    font-size: 10pt;
                    white-space: nowrap;
                }
                th { 
                    background-color: #4CAF50; 
                    color: white; 
                    font-weight: bold; 
                    font-size: 10pt;
                }
                .title { font-size: 10pt; font-weight: bold; margin-bottom: 5px; }
                .period { font-size: 10pt; margin-bottom: 10px; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
                .number { text-align: right; }
                .col-no { width: 90px; }
                .col-month { width: 200px; }
                .col-amount { width: 300px; }
            </style>
        </head>
        <body>
            <div class="title">Monthly Branch Revenue Report</div>
            <div class="period">Year Comparison: ' . $previousYear . ' vs ' . $year . ' | Branch: ' . htmlspecialchars($branchDisplay) . ' | Category: ' . htmlspecialchars($category) . '</div>
            <br>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Month</th>
                        <th style="text-align: right;">' . $previousYear . ' (Rp)</th>
                        <th style="text-align: right;">' . $year . ' (Rp)</th>
                        <th style="text-align: right;">Growth (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $totalPreviousYear = 0;
        $totalCurrentYear = 0;

        for ($month = 1; $month <= 12; $month++) {
            $currentRevenue = $currentYearMap->get($month);
            $previousRevenue = $previousYearMap->get($month);

            $currentValue = $currentRevenue ? $currentRevenue->total_revenue : 0;
            $previousValue = $previousRevenue ? $previousRevenue->total_revenue : 0;

            $totalPreviousYear += $previousValue;
            $totalCurrentYear += $currentValue;

            $growth = 0;
            if ($previousValue > 0) {
                $growth = (($currentValue - $previousValue) / $previousValue) * 100;
            }

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . $monthLabels[$month - 1] . '</td>
                <td class="number">' . number_format($previousValue, 2, '.', ',') . '</td>
                <td class="number">' . number_format($currentValue, 2, '.', ',') . '</td>
                <td class="number">' . number_format($growth, 2, '.', ',') . '%</td>
            </tr>';
        }

        $totalGrowth = 0;
        if ($totalPreviousYear > 0) {
            $totalGrowth = (($totalCurrentYear - $totalPreviousYear) / $totalPreviousYear) * 100;
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalPreviousYear, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalCurrentYear, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalGrowth, 2, '.', ',') . '%</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $branch = $request->input('branch', 'National');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $today = date('Y-m-d');
            $endDate = min($endDate, $today);
        }

        $previousYear = $year - 1;

        // Get current year data
        $currentYearData = $this->getMonthlyRevenueData($startDate, $endDate, $category, $branch);

        // Get previous year data
        $previousStartDate = $previousYear . '-01-01';
        $previousEndDate = $previousYear . '-12-31';
        $previousYearData = $this->getMonthlyRevenueData($previousStartDate, $previousEndDate, $category, $branch);

        // Month labels
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

        // Map data by month
        $currentYearMap = collect($currentYearData)->keyBy('month_number');
        $previousYearMap = collect($previousYearData)->keyBy('month_number');

        $branchDisplay = $branch === 'National' ? 'National' : ChartHelper::getBranchDisplayName($branch);

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
                <div class="title">Monthly Branch Revenue Report</div>
                <div class="period">Year Comparison: ' . $previousYear . ' vs ' . $year . ' | Branch: ' . htmlspecialchars($branchDisplay) . ' | Category: ' . htmlspecialchars($category) . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 140px;">Month</th>
                        <th style="width: 200px; text-align: right;">' . $previousYear . '</th>
                        <th style="width: 200px; text-align: right;">' . $year . '</th>
                        <th style="width: 160px; text-align: right;">Growth (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $totalPreviousYear = 0;
        $totalCurrentYear = 0;

        for ($month = 1; $month <= 12; $month++) {
            $currentRevenue = $currentYearMap->get($month);
            $previousRevenue = $previousYearMap->get($month);

            $currentValue = $currentRevenue ? $currentRevenue->total_revenue : 0;
            $previousValue = $previousRevenue ? $previousRevenue->total_revenue : 0;

            $totalPreviousYear += $previousValue;
            $totalCurrentYear += $currentValue;

            $growth = 0;
            if ($previousValue > 0) {
                $growth = (($currentValue - $previousValue) / $previousValue) * 100;
            }

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . $monthLabels[$month - 1] . '</td>
                <td class="number">' . number_format($previousValue, 2, '.', ',') . '</td>
                <td class="number">' . number_format($currentValue, 2, '.', ',') . '</td>
                <td class="number">' . number_format($growth, 2, '.', ',') . '%</td>
            </tr>';
        }

        $totalGrowth = 0;
        if ($totalPreviousYear > 0) {
            $totalGrowth = (($totalCurrentYear - $totalPreviousYear) / $totalPreviousYear) * 100;
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalPreviousYear, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalCurrentYear, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalGrowth, 2, '.', ',') . '%</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Monthly_Branch_Revenue_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $branchDisplay) . '_' . str_replace(' ', '_', $category) . '.pdf';

        return $pdf->download($filename);
    }
}

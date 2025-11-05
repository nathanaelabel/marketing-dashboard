<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChartHelper;
use PDOException;

class NationalYearlyController extends Controller
{
    public function getData(Request $request)
    {
        // Increase PHP execution time limit for heavy queries
        set_time_limit(120); // 2 minutes

        $year = null;
        $startDate = null;
        $endDate = null;
        $dateRanges = null;

        try {
            $year = $request->input('year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $today = date('Y-m-d');
            $currentYear = date('Y');

            if ($year) {
                // Validate year input
                if (!is_numeric($year) || $year < 2020 || $year > 2050) {
                    Log::warning('NationalYearlyController: Invalid year parameter', ['year' => $year]);
                    $year = $currentYear;
                }

                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';

                if ($year == $currentYear) {
                    $endDate = $today;
                }
            } else {
                $startDate = $startDate ?: date('Y') . '-01-01';
                $endDate = $endDate ?: $today;
                $year = date('Y', strtotime($startDate));
            }

            $previousYear = $year - 1;
            $category = $request->get('category', 'MIKA');
            $type = $request->get('type', 'NETTO'); // Default to NETTO

            // Validate category
            if (!in_array($category, ['MIKA', 'SPARE PART'])) {
                $category = 'MIKA';
            }

            // Validate type
            if (!in_array($type, ['NETTO', 'BRUTO'])) {
                $type = 'NETTO';
            }

            // Calculate date ranges with error handling
            try {
                $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
            } catch (\Exception $e) {
                Log::error('NationalYearlyController: Error calculating date ranges', [
                    'end_date' => $endDate,
                    'previous_year' => $previousYear,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            // Fetch data with timeout handling
            try {
                $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
            } catch (\Exception $e) {
                Log::error('NationalYearlyController: Error fetching current year data', [
                    'start' => $dateRanges['current']['start'],
                    'end' => $dateRanges['current']['end'],
                    'category' => $category,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            try {
                $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category, $type);
            } catch (\Exception $e) {
                Log::error('NationalYearlyController: Error fetching previous year data', [
                    'start' => $dateRanges['previous']['start'],
                    'end' => $dateRanges['previous']['end'],
                    'category' => $category,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            $formattedData = $this->formatYearlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

            return response()->json($formattedData);
        } catch (\PDOException $e) {
            Log::error('NationalYearlyController getData PDO error: ' . $e->getMessage(), [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'date_ranges' => $dateRanges ?? null,
                'category' => $request->get('category'),
                'type' => $request->get('type'),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Database connection timeout. Please try again.',
                'message' => 'The request took too long to process. Please refresh the page.'
            ], 500);
        } catch (\Error $e) {
            // Handle fatal errors like maximum execution time exceeded
            $errorMessage = $e->getMessage();
            $isTimeout = strpos($errorMessage, 'Maximum execution time') !== false ||
                strpos($errorMessage, 'execution time') !== false;

            Log::error('NationalYearlyController getData Fatal error: ' . $errorMessage, [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'date_ranges' => $dateRanges ?? null,
                'category' => $request->get('category'),
                'type' => $request->get('type'),
                'is_timeout' => $isTimeout,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => $isTimeout ? 'Request timeout' : 'Server error',
                'message' => $isTimeout
                    ? 'The query is taking too long to execute. Please try again or contact support if the problem persists.'
                    : 'An unexpected error occurred. Please try again.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('NationalYearlyController getData error: ' . $e->getMessage(), [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'date_ranges' => $dateRanges ?? null,
                'category' => $request->get('category'),
                'type' => $request->get('type'),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch national yearly data',
                'message' => 'An error occurred while processing your request. Please try again.'
            ], 500);
        }
    }

    public function getCategories()
    {
        // Delegate to shared helper to avoid duplication and keep logic centralized
        $categories = ChartHelper::getCategories();
        return response()->json($categories);
    }

    private function getRevenueData($startDate, $endDate, $category, $type = 'BRUTO')
    {
        try {
            // Set statement timeout for this query (in milliseconds for PostgreSQL)
            // 600000 = 10 minutes timeout
            // Only set if using PostgreSQL
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    // Set statement timeout to 2 minutes (120000 milliseconds)
                    DB::statement("SET statement_timeout = 120000");
                }
            } catch (\Exception $e) {
                // Ignore if statement timeout is not supported
                Log::debug('Could not set statement timeout', ['error' => $e->getMessage()]);
            }

            if ($type === 'NETTO') {
                // Netto query - includes returns (CNC documents) as negative values
                $query = "
                    SELECT
                        org.name AS branch_name,
                        COALESCE(SUM(CASE
                            WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
                            WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
                        END), 0) AS total_revenue
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
                    AND inv.dateinvoiced::date >= ? AND inv.dateinvoiced::date <= ?
                    AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
                    AND pc.name = ?
                    GROUP BY
                        org.name
                    ORDER BY
                        org.name
                ";
            } else {
                // Bruto query - original query (only INC documents)
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
                    AND inv.dateinvoiced::date >= ? AND inv.dateinvoiced::date <= ?
                    AND inv.documentno LIKE 'INC%'
                    AND pc.name = ?
                    GROUP BY
                        org.name
                    ORDER BY
                        org.name
                ";
            }

            $result = DB::select($query, [$startDate, $endDate, $category]);

            // Reset statement timeout (only for PostgreSQL)
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 0");
                }
            } catch (\Exception $e) {
                // Ignore reset error
            }

            return $result;
        } catch (\PDOException $e) {
            // Reset statement timeout on error (only for PostgreSQL)
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 0");
                }
            } catch (\Exception $resetError) {
                // Ignore reset error
            }

            Log::error('NationalYearlyController getRevenueData PDO error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'type' => $type,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Reset statement timeout on error (only for PostgreSQL)
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 0");
                }
            } catch (\Exception $resetError) {
                // Ignore reset error
            }

            Log::error('NationalYearlyController getRevenueData error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
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

        // If no data at all, ensure we have empty arrays and default values
        if ($allBranches->isEmpty()) {
            $labels = [];
            $currentYearValues = [];
            $previousYearValues = [];
            $maxValue = 0;
        } else {
            // Get branch abbreviations
            $labels = $allBranches->map(function ($name) {
                return ChartHelper::getBranchAbbreviation($name);
            });

            // Get max value for Y-axis scaling
            $maxValue = 0;
            if (!empty($currentYearValues) && !empty($previousYearValues)) {
                $maxValue = max(max($currentYearValues), max($previousYearValues));
            } elseif (!empty($currentYearValues)) {
                $maxValue = max($currentYearValues);
            } elseif (!empty($previousYearValues)) {
                $maxValue = max($previousYearValues);
            }
        }

        // Use ChartHelper for Y-axis configuration
        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, array_merge($currentYearValues, $previousYearValues));
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

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

    public function exportExcel(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $type = $request->input('type', 'BRUTO');
        $today = date('Y-m-d');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $endDate = $today;
        }

        $previousYear = $year - 1;

        $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
        $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
        $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category, $type);

        // Get all unique branches from both datasets
        $allBranches = collect($currentYearData)->pluck('branch_name')
            ->merge(collect($previousYearData)->pluck('branch_name'))
            ->unique()
            ->sort()
            ->values();

        // Map data for each year
        $currentYearMap = collect($currentYearData)->keyBy('branch_name');
        $previousYearMap = collect($previousYearData)->keyBy('branch_name');

        $filename = 'Penjualan_Tahunan_Nasional_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $category) . '_' . $type . '.xls';

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
                            <x:Name>Penjualan Tahunan Nasional</x:Name>
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
                .col-branch { width: 270px; }
                .col-code { width: 160px; }
                .col-amount { width: 300px; }
            </style>
        </head>
        <body>
            <div class="title">Penjualan Tahunan Nasional</div>
            <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
            <br>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Cabang</th>
                        <th>Kode Cabang</th>
                        <th style="text-align: right;">' . $previousYear . ' (Rp)</th>
                        <th style="text-align: right;">' . $year . ' (Rp)</th>
                        <th style="text-align: right;">Growth (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $totalPreviousYear = 0;
        $totalCurrentYear = 0;

        foreach ($allBranches as $branch) {
            $currentRevenue = $currentYearMap->get($branch);
            $previousRevenue = $previousYearMap->get($branch);

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
                <td>' . htmlspecialchars($branch) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branch)) . '</td>
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
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
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
        $type = $request->input('type', 'BRUTO');
        $today = date('Y-m-d');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $endDate = $today;
        }

        $previousYear = $year - 1;

        $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
        $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
        $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category, $type);

        // Get all unique branches from both datasets
        $allBranches = collect($currentYearData)->pluck('branch_name')
            ->merge(collect($previousYearData)->pluck('branch_name'))
            ->unique()
            ->sort()
            ->values();

        // Map data for each year
        $currentYearMap = collect($currentYearData)->keyBy('branch_name');
        $previousYearMap = collect($previousYearData)->keyBy('branch_name');

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
                <div class="title">Penjualan Tahunan Nasional</div>
                <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th style="width: 150px;">Nama Cabang</th>
                        <th style="width: 50px;">Kode Cabang</th>
                        <th style="width: 100px; text-align: right;">' . $previousYear . '</th>
                        <th style="width: 100px; text-align: right;">' . $year . '</th>
                        <th style="width: 80px; text-align: right;">Growth (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $totalPreviousYear = 0;
        $totalCurrentYear = 0;

        foreach ($allBranches as $branch) {
            $currentRevenue = $currentYearMap->get($branch);
            $previousRevenue = $previousYearMap->get($branch);

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
                <td>' . htmlspecialchars($branch) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branch)) . '</td>
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
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
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

        $filename = 'Penjualan_Tahunan_Nasional_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $category) . '_' . $type . '.pdf';

        return $pdf->download($filename);
    }
}

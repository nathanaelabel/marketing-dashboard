<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ChartHelper;

class MonthlyBranchController extends Controller
{
    private function formatIndonesianDate($date)
    {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $dateObj = new \DateTime($date);
        $day = (int)$dateObj->format('j');
        $month = (int)$dateObj->format('n');

        return $day . ' ' . $monthNames[$month];
    }

    public function getData(Request $request)
    {
        // Tingkatkan batas waktu eksekusi untuk query berat
        set_time_limit(120); // 2 minutes

        $year = null;
        $startDate = null;
        $endDate = null;

        try {
            $year = $request->input('year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if ($year) {
                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';

                // Jika tahun berjalan, batasi tanggal akhir ke H-1 karena dashboard diupdate setiap malam
                $currentYear = date('Y');
                if ($year == $currentYear) {
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $endDate = min($endDate, $yesterday);
                }
            } else {
                $startDate = $startDate ?: date('Y') . '-01-01';
                $endDate = $endDate ?: date('Y-m-d', strtotime('-1 day'));
                $year = date('Y', strtotime($startDate));
            }

            $previousYear = $year - 1;
            $category = $request->get('category', 'MIKA');
            $branch = $request->get('branch', 'National');
            $type = $request->get('type', 'NETTO');

            $currentYearData = $this->getMonthlyRevenueData($startDate, $endDate, $category, $branch, $type);

            $previousStartDate = $previousYear . '-01-01';
            $previousEndDate = $previousYear . '-12-31';
            $previousYearData = $this->getMonthlyRevenueData($previousStartDate, $previousEndDate, $category, $branch, $type);

            $formattedData = $this->formatMonthlyComparisonData($currentYearData, $previousYearData, $year, $previousYear);

            return response()->json($formattedData);
        } catch (\PDOException $e) {
            Log::error('MonthlyBranchController getData PDO error: ' . $e->getMessage(), [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'category' => $request->get('category'),
                'branch' => $request->get('branch'),
                'type' => $request->get('type'),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Database connection timeout. Please try again.',
                'message' => 'The request took too long to process. Please refresh the page.'
            ], 500);
        } catch (\Error $e) {
            $errorMessage = $e->getMessage();
            $isTimeout = strpos($errorMessage, 'Maximum execution time') !== false ||
                strpos($errorMessage, 'execution time') !== false;

            Log::error('MonthlyBranchController getData Fatal error: ' . $errorMessage, [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'category' => $request->get('category'),
                'branch' => $request->get('branch'),
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
            Log::error('MonthlyBranchController getData error: ' . $e->getMessage(), [
                'year' => $year ?? $request->get('year'),
                'start_date' => $startDate ?? null,
                'end_date' => $endDate ?? null,
                'category' => $request->get('category'),
                'branch' => $request->get('branch'),
                'type' => $request->get('type'),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch monthly branch data',
                'message' => 'An error occurred while processing your request. Please try again.'
            ], 500);
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

            $branchOptions = collect([
                [
                    'value' => 'National',
                    'display' => 'National'
                ]
            ]);

            $individualBranches = $branches->map(function ($branch) {
                return [
                    'value' => $branch,
                    'display' => ChartHelper::getBranchDisplayName($branch)
                ];
            });

            $allOptions = $branchOptions->merge($individualBranches);

            return response()->json($allOptions);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getMonthlyRevenueData($startDate, $endDate, $category, $branch, $type = 'BRUTO')
    {
        $startTime = microtime(true);
        $branchParam = ($branch === 'National') ? null : $branch;

        try {
            if ($type === 'NETTO') {
                $results = DB::select(
                    'SELECT * FROM sp_get_monthly_revenue_netto(?, ?, ?, ?)',
                    [$startDate, $endDate, $category, $branchParam]
                );
            } else {
                $results = DB::select(
                    'SELECT * FROM sp_get_monthly_revenue_bruto(?, ?, ?, ?)',
                    [$startDate, $endDate, $category, $branchParam]
                );
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("MonthlyBranch: getMonthlyRevenueData ({$type}) took {$duration}ms", [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'branch' => $branch,
                'rows' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('MonthlyBranchController getMonthlyRevenueData error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'branch' => $branch,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function formatMonthlyComparisonData($currentYearData, $previousYearData, $year, $previousYear)
    {
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

        $currentYearMap = collect($currentYearData)->keyBy('month_number');
        $previousYearMap = collect($previousYearData)->keyBy('month_number');

        $currentYearValues = [];
        $previousYearValues = [];

        for ($month = 1; $month <= 12; $month++) {
            $currentRevenue = $currentYearMap->get($month);
            $previousRevenue = $previousYearMap->get($month);

            $currentYearValues[] = $currentRevenue ? $currentRevenue->total_revenue : 0;
            $previousYearValues[] = $previousRevenue ? $previousRevenue->total_revenue : 0;
        }

        $maxValue = max(max($currentYearValues), max($previousYearValues));

        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, array_merge($currentYearValues, $previousYearValues));
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);

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
        // Increase execution time for export operations
        set_time_limit(300); // 5 minutes for export
        ini_set('max_execution_time', 300);

        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $type = $request->input('type', 'BRUTO');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            // Gunakan H-1 karena dashboard diupdate setiap malam
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $endDate = min($endDate, $yesterday);
        }

        $previousYear = $year - 1;

        $branches = ChartHelper::getLocations();

        $previousStartDate = $previousYear . '-01-01';
        $previousEndDate = $previousYear . '-12-31';

        $startTime = microtime(true);

        if ($type === 'NETTO') {
            $allData = DB::select(
                'SELECT * FROM sp_get_monthly_revenue_export_netto(?, ?, ?, ?, ?)',
                [$previousStartDate, $previousEndDate, $startDate, $endDate, $category]
            );
        } else {
            $allData = DB::select(
                'SELECT * FROM sp_get_monthly_revenue_export_bruto(?, ?, ?, ?, ?)',
                [$previousStartDate, $previousEndDate, $startDate, $endDate, $category]
            );
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("MonthlyBranch: exportExcel ({$type}) query took {$duration}ms", [
            'prev_period' => "{$previousStartDate} - {$previousEndDate}",
            'curr_period' => "{$startDate} - {$endDate}",
            'category' => $category,
            'rows' => count($allData)
        ]);

        $allData = ChartHelper::sortByBranchOrder(collect($allData), 'branch_name')->all();

        // Deteksi bulan terakhir yang tersedia di data tahun berjalan
        $lastAvailableMonth = 0;
        foreach ($allData as $row) {
            if ((int)$row->year_number == $year) {
                $lastAvailableMonth = max($lastAvailableMonth, (int)$row->month_number);
            }
        }

        if ($lastAvailableMonth == 0) {
            $lastAvailableMonth = 12;
        }

        // Untuk perbandingan tahun lengkap gunakan 12 bulan, tahun berjalan gunakan bulan terakhir yang tersedia
        $monthsToShow = 12;
        if ($year == $currentYear && $lastAvailableMonth < 12) {
            $monthsToShow = $lastAvailableMonth;
        }

        $isCompleteYear = ($endDate == $year . '-12-31');

        if ($isCompleteYear) {
            $dateRangeInfo = 'Periode: 1 Januari - 31 Desember ' . $previousYear . ' VS 1 Januari - 31 Desember ' . $year;
        } else {
            $currentEndDateFormatted = $this->formatIndonesianDate($endDate);

            $currentEndDate = new \DateTime($endDate);
            $previousEndDateStr = $previousYear . $currentEndDate->format('-m-d');
            $previousEndDateFormatted = $this->formatIndonesianDate($previousEndDateStr);

            $dateRangeInfo = 'Periode: 1 Januari - ' . $previousEndDateFormatted . ' ' . $previousYear . ' VS 1 Januari - ' . $currentEndDateFormatted . ' ' . $year;
        }

        $allBranchesData = [];
        foreach ($branches as $branch) {
            $branchData = [
                'branch' => $branch,
                'code' => ChartHelper::getBranchAbbreviation($branch),
                'current_year' => array_fill(1, 12, 0),
                'previous_year' => array_fill(1, 12, 0)
            ];

            foreach ($allData as $row) {
                if ($row->branch_name === $branch) {
                    $month = (int)$row->month_number;
                    $year_num = (int)$row->year_number;

                    if ($year_num == $year) {
                        $branchData['current_year'][$month] = $row->total_revenue;
                    } elseif ($year_num == $previousYear) {
                        $branchData['previous_year'][$month] = $row->total_revenue;
                    }
                }
            }

            $allBranchesData[] = $branchData;
        }

        $filename = 'Penjualan_Bulanan_Cabang_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $category) . '_' . $type . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $monthLabels = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        $html = '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Penjualan Bulanan Cabang</x:Name>
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
                body { font-family: Verdana, sans-serif; }
                table { border-collapse: collapse; }
                th, td {
                    border: 1px solid #000;
                    padding: 6px 8px;
                    text-align: left;
                    font-family: Verdana, sans-serif;
                    font-size: 10pt;
                }
                th {
                    background-color: #D3D3D3;
                    color: #000;
                    font-weight: bold;
                    text-align: center;
                    vertical-align: middle;
                }
                .title {
                    font-family: Verdana, sans-serif;
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 8px;
                }
                .period {
                    font-family: Verdana, sans-serif;
                    font-size: 12pt;
                    margin-bottom: 15px;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #E8E8E8;
                }
                .number { text-align: right; }
                .month-header {
                    font-family: Verdana, sans-serif;
                    font-size: 12pt;
                    font-weight: bold;
                    text-align: center;
                }
                .year-subheader {
                    font-family: Verdana, sans-serif;
                    font-size: 11pt;
                    text-align: center;
                }
                .branch-code {
                    text-align: center;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="title">PENJUALAN BULANAN CABANG</div>
            <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
            <div class="period" style="font-size: 10pt; color: #666;">' . htmlspecialchars($dateRangeInfo) . '</div>
            <br>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="font-size: 12pt;">CAB</th>';

        for ($month = 1; $month <= $monthsToShow; $month++) {
            $html .= '<th colspan="3" class="month-header">' . $monthLabels[$month - 1] . '</th>';
        }

        $html .= '<th colspan="3" class="month-header">TOTAL</th>';

        $html .= '
                    </tr>
                    <tr>';

        for ($month = 1; $month <= $monthsToShow; $month++) {
            $html .= '<th class="year-subheader">' . $previousYear . '</th>';
            $html .= '<th class="year-subheader">' . $year . '</th>';
            $html .= '<th class="year-subheader">GROWTH %</th>';
        }

        $html .= '<th class="year-subheader">' . $previousYear . '</th>';
        $html .= '<th class="year-subheader">' . $year . '</th>';
        $html .= '<th class="year-subheader">GROWTH %</th>';

        $html .= '
                    </tr>
                </thead>
                <tbody>';

        $totalPreviousYear = array_fill(1, 12, 0);
        $totalCurrentYear = array_fill(1, 12, 0);
        $grandTotalPrevious = 0;
        $grandTotalCurrent = 0;

        foreach ($allBranchesData as $branchData) {
            $html .= '<tr>
                <td class="branch-code">' . htmlspecialchars($branchData['code']) . '</td>';

            $branchTotalPrevious = 0;
            $branchTotalCurrent = 0;

            for ($month = 1; $month <= $monthsToShow; $month++) {
                $prevValue = $branchData['previous_year'][$month];
                $currValue = $branchData['current_year'][$month];

                $branchTotalPrevious += $prevValue;
                $branchTotalCurrent += $currValue;
                $totalPreviousYear[$month] += $prevValue;
                $totalCurrentYear[$month] += $currValue;

                $monthGrowth = 0;
                if ($prevValue > 0) {
                    $monthGrowth = (($currValue - $prevValue) / $prevValue) * 100;
                }

                $html .= '<td class="number">' . number_format($prevValue, 0, '.', ',') . '</td>';
                $html .= '<td class="number">' . number_format($currValue, 0, '.', ',') . '</td>';
                $html .= '<td class="number">' . number_format($monthGrowth, 2, '.', ',') . '</td>';
            }

            $grandTotalPrevious += $branchTotalPrevious;
            $grandTotalCurrent += $branchTotalCurrent;

            $branchTotalGrowth = 0;
            if ($branchTotalPrevious > 0) {
                $branchTotalGrowth = (($branchTotalCurrent - $branchTotalPrevious) / $branchTotalPrevious) * 100;
            }

            $html .= '<td class="number">' . number_format($branchTotalPrevious, 0, '.', ',') . '</td>';
            $html .= '<td class="number">' . number_format($branchTotalCurrent, 0, '.', ',') . '</td>';
            $html .= '<td class="number">' . number_format($branchTotalGrowth, 2, '.', ',') . '</td>';

            $html .= '</tr>';
        }

        // TOTAL row
        $html .= '
                    <tr class="total-row">
                        <td class="branch-code"><strong>TOTAL</strong></td>';

        for ($month = 1; $month <= $monthsToShow; $month++) {
            $prevTotal = $totalPreviousYear[$month];
            $currTotal = $totalCurrentYear[$month];

            $monthTotalGrowth = 0;
            if ($prevTotal > 0) {
                $monthTotalGrowth = (($currTotal - $prevTotal) / $prevTotal) * 100;
            }

            $html .= '<td class="number"><strong>' . number_format($prevTotal, 0, '.', ',') . '</strong></td>';
            $html .= '<td class="number"><strong>' . number_format($currTotal, 0, '.', ',') . '</strong></td>';
            $html .= '<td class="number"><strong>' . number_format($monthTotalGrowth, 2, '.', ',') . '</strong></td>';
        }

        $grandTotalGrowth = 0;
        if ($grandTotalPrevious > 0) {
            $grandTotalGrowth = (($grandTotalCurrent - $grandTotalPrevious) / $grandTotalPrevious) * 100;
        }

        $html .= '<td class="number"><strong>' . number_format($grandTotalPrevious, 0, '.', ',') . '</strong></td>';
        $html .= '<td class="number"><strong>' . number_format($grandTotalCurrent, 0, '.', ',') . '</strong></td>';
        $html .= '<td class="number"><strong>' . number_format($grandTotalGrowth, 2, '.', ',') . '</strong></td>';

        $html .= '
                    </tr>
                </tbody>
            </table>
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        // Increase execution time for export operations
        set_time_limit(300); // 5 minutes for export
        ini_set('max_execution_time', 300);

        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $branch = $request->input('branch', 'National');
        $type = $request->input('type', 'BRUTO');
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $endDate = min($endDate, $yesterday);
        }

        $previousYear = $year - 1;

        $currentYearData = $this->getMonthlyRevenueData($startDate, $endDate, $category, $branch, $type);

        $previousStartDate = $previousYear . '-01-01';
        $previousEndDate = $previousYear . '-12-31';
        $previousYearData = $this->getMonthlyRevenueData($previousStartDate, $previousEndDate, $category, $branch, $type);

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

        $currentYearMap = collect($currentYearData)->keyBy('month_number');
        $previousYearMap = collect($previousYearData)->keyBy('month_number');

        $branchDisplay = $branch === 'National' ? 'National' : ChartHelper::getBranchDisplayName($branch);

        $isCompleteYear = ($endDate == $year . '-12-31');

        if ($isCompleteYear) {
            $dateRangeInfo = 'Periode: 1 Januari - 31 Desember ' . $previousYear . ' VS 1 Januari - 31 Desember ' . $year;
        } else {
            $currentEndDateFormatted = $this->formatIndonesianDate($endDate);

            $currentEndDate = new \DateTime($endDate);
            $previousEndDateStr = $previousYear . $currentEndDate->format('-m-d');
            $previousEndDateFormatted = $this->formatIndonesianDate($previousEndDateStr);

            $dateRangeInfo = 'Periode: 1 Januari - ' . $previousEndDateFormatted . ' ' . $previousYear . ' VS 1 Januari - ' . $currentEndDateFormatted . ' ' . $year;
        }

        // Create HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 20px; }
                body {
                    font-family: Verdana, sans-serif;
                    font-size: 9pt;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .title {
                    font-family: Verdana, sans-serif;
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .period {
                    font-family: Verdana, sans-serif;
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
                    padding: 6px 8px;
                    text-align: left;
                    font-family: Verdana, sans-serif;
                    font-size: 8pt;
                }
                th {
                    background-color: #F5F5F5;
                    color: #000;
                    font-weight: bold;
                    text-align: center;
                    vertical-align: middle;
                }
                .number { text-align: right; }
                .total-row {
                    font-weight: bold;
                    background-color: #E8E8E8;
                }
                .total-row td {
                    border-top: 2px solid #333;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">PENJUALAN BULANAN CABANG</div>
                <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Cabang ' . htmlspecialchars($branchDisplay) . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
                <div class="period" style="font-size: 9pt; color: #666;">' . htmlspecialchars($dateRangeInfo) . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: right;">NO</th>
                        <th style="width: 140px;">MONTH</th>
                        <th style="width: 200px; text-align: right;">' . $previousYear . '</th>
                        <th style="width: 200px; text-align: right;">' . $year . '</th>
                        <th style="width: 160px; text-align: right;">GROWTH (%)</th>
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
                <td style="text-align: right;">' . $no++ . '</td>
                <td>' . $monthLabels[$month - 1] . '</td>
                <td class="number">' . number_format($previousValue, 0, '.', ',') . '</td>
                <td class="number">' . number_format($currentValue, 0, '.', ',') . '</td>
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
                        <td class="number"><strong>' . number_format($totalPreviousYear, 0, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalCurrentYear, 0, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalGrowth, 2, '.', ',') . '%</strong></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Penjualan_Bulanan_Cabang_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $branchDisplay) . '_' . str_replace(' ', '_', $category) . '_' . $type . '.pdf';

        return $pdf->download($filename);
    }
}

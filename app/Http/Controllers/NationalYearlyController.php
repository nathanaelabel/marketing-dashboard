<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ChartHelper;
use PDOException;

class NationalYearlyController extends Controller
{
    public function getData(Request $request)
    {
        // Tingkatkan batas waktu eksekusi untuk query berat
        set_time_limit(120); // 2 menit

        $year = null;
        $startDate = null;
        $endDate = null;
        $dateRanges = null;

        try {
            $year = $request->input('year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $currentYear = date('Y');

            if ($year) {
                if (!is_numeric($year) || $year < 2020 || $year > 2050) {
                    Log::warning('NationalYearlyController: Parameter tahun tidak valid', ['year' => $year]);
                    $year = $currentYear;
                }

                $startDate = $year . '-01-01';
                $endDate = $year . '-12-31';

                if ($year == $currentYear) {
                    $endDate = $yesterday;
                }
            } else {
                $startDate = $startDate ?: date('Y') . '-01-01';
                $endDate = $endDate ?: $yesterday;
                $year = date('Y', strtotime($startDate));
            }

            $previousYear = $year - 1;
            $category = $request->get('category', 'MIKA');
            $type = $request->get('type', 'NETTO');

            if (!in_array($category, ['MIKA', 'SPARE PART'])) {
                $category = 'MIKA';
            }

            if (!in_array($type, ['NETTO', 'BRUTO'])) {
                $type = 'NETTO';
            }

            // Hitung rentang tanggal dengan penanganan kesalahan
            try {
                $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
            } catch (\Exception $e) {
                Log::error('NationalYearlyController: Kesalahan menghitung rentang tanggal', [
                    'end_date' => $endDate,
                    'previous_year' => $previousYear,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            // Ambil data dengan penanganan timeout
            try {
                $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
            } catch (\Exception $e) {
                Log::error('NationalYearlyController: Kesalahan mengambil data tahun saat ini', [
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
                Log::error('NationalYearlyController: Kesalahan mengambil data tahun sebelumnya', [
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
                'error' => 'Koneksi database timeout. Silakan coba lagi.',
                'message' => 'Permintaan membutuhkan waktu lama untuk diproses. Silakan refresh halaman.'
            ], 500);
        } catch (\Error $e) {
            // Tangani kesalahan fatal seperti waktu eksekusi maksimum terlampaui
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
                    ? 'Query membutuhkan waktu lama untuk dieksekusi. Silakan coba lagi atau hubungi dukungan jika masalah berlanjut.'
                    : 'Terjadi kesalahan tidak terduga. Silakan coba lagi.'
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
                'error' => 'Gagal mengambil data tahunan nasional',
                'message' => 'Terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.'
            ], 500);
        }
    }

    public function getCategories()
    {
        $categories = ChartHelper::getCategories();
        return response()->json($categories);
    }

    private function getRevenueData($startDate, $endDate, $category, $type = 'BRUTO')
    {
        $startTime = microtime(true);

        try {
            if ($type === 'NETTO') {
                $result = DB::select(
                    'SELECT * FROM sp_get_national_yearly_netto(?, ?, ?)',
                    [$startDate, $endDate, $category]
                );
            } else {
                $result = DB::select(
                    'SELECT * FROM sp_get_national_yearly_bruto(?, ?, ?)',
                    [$startDate, $endDate, $category]
                );
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("NationalYearly: getRevenueData ({$type}) took {$duration}ms", [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category' => $category,
                'rows' => count($result)
            ]);

            $sortedResult = ChartHelper::sortByBranchOrder(collect($result), 'branch_name');

            return $sortedResult->all();
        } catch (\Exception $e) {
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
        // Ambil semua cabang unik dari kedua dataset
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
            // Ambil singkatan cabang
            $labels = $allBranches->map(function ($name) {
                return ChartHelper::getBranchAbbreviation($name);
            });

            // Ambil nilai maksimum untuk skala sumbu Y
            $maxValue = 0;
            if (!empty($currentYearValues) && !empty($previousYearValues)) {
                $maxValue = max(max($currentYearValues), max($previousYearValues));
            } elseif (!empty($currentYearValues)) {
                $maxValue = max($currentYearValues);
            } elseif (!empty($previousYearValues)) {
                $maxValue = max($previousYearValues);
            }
        }

        $yAxisConfig = ChartHelper::getYAxisConfig($maxValue, null, array_merge($currentYearValues, $previousYearValues));
        $suggestedMax = ChartHelper::calculateSuggestedMax($maxValue, $yAxisConfig['divisor']);
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
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $endDate = $yesterday;
        }

        $previousYear = $year - 1;

        $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
        $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
        $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category, $type);

        // Ambil semua cabang unik dari kedua dataset
        $allBranches = collect($currentYearData)->pluck('branch_name')
            ->merge(collect($previousYearData)->pluck('branch_name'))
            ->unique()
            ->sort()
            ->values();

        $currentYearMap = collect($currentYearData)->keyBy('branch_name');
        $previousYearMap = collect($previousYearData)->keyBy('branch_name');

        $currentYearEndDate = date('Y-m-d', strtotime($dateRanges['current']['end']));
        $previousYearEndDate = date('Y-m-d', strtotime($dateRanges['previous']['end']));

        $isCompleteYear = ($currentYearEndDate == $year . '-12-31');

        if ($isCompleteYear) {
            // Complete year comparison (e.g., 2023-2024)
            $dateRangeInfo = 'Periode: 1 Januari - 31 Desember ' . $previousYear . ' VS 1 Januari - 31 Desember ' . $year;
        } else {
            // Partial year comparison (e.g., 2024-2025 where current year is incomplete)
            $currentEndDateFormatted = date('j F', strtotime($currentYearEndDate));
            $previousEndDateFormatted = date('j F', strtotime($previousYearEndDate));
            $dateRangeInfo = 'Periode: 1 Januari - ' . $previousEndDateFormatted . ' ' . $previousYear . ' VS 1 Januari - ' . $currentEndDateFormatted . ' ' . $year;
        }

        $filename = 'Penjualan_Tahunan_Nasional_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $category) . '_' . $type . '.xls';

        $allBranches = ChartHelper::sortByBranchOrder($allBranches, null);

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
                .total-row { font-weight: bold; background-color: #E8E8E8; }
                .number { text-align: right; }
                .col-no { width: 90px; }
                .col-branch { width: 270px; }
                .col-code { width: 160px; }
                .col-amount { width: 300px; }
            </style>
        </head>
        <body>
            <div class="title">PENJUALAN TAHUNAN NASIONAL</div>
            <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
            <div class="period" style="font-size: 10pt; color: #666;">' . htmlspecialchars($dateRangeInfo) . '</div>
            <br>
            <table>
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA CABANG</th>
                        <th>KODE CABANG</th>
                        <th style="text-align: right;">' . $previousYear . ' (RP)</th>
                        <th style="text-align: right;">' . $year . ' (RP)</th>
                        <th style="text-align: right;">GROWTH (%)</th>
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
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');
        $type = $request->input('type', 'BRUTO');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $currentYear = date('Y');

        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($year == $currentYear) {
            $endDate = $yesterday;
        }

        $previousYear = $year - 1;

        $dateRanges = ChartHelper::calculateFairComparisonDateRanges($endDate, $previousYear);
        $currentYearData = $this->getRevenueData($dateRanges['current']['start'], $dateRanges['current']['end'], $category, $type);
        $previousYearData = $this->getRevenueData($dateRanges['previous']['start'], $dateRanges['previous']['end'], $category, $type);

        // Ambil semua cabang unik dari kedua dataset
        $allBranches = collect($currentYearData)->pluck('branch_name')
            ->merge(collect($previousYearData)->pluck('branch_name'))
            ->unique()
            ->sort()
            ->values();

        $currentYearMap = collect($currentYearData)->keyBy('branch_name');
        $previousYearMap = collect($previousYearData)->keyBy('branch_name');
        $currentYearEndDate = date('Y-m-d', strtotime($dateRanges['current']['end']));
        $previousYearEndDate = date('Y-m-d', strtotime($dateRanges['previous']['end']));

        $isCompleteYear = ($currentYearEndDate == $year . '-12-31');

        if ($isCompleteYear) {
            // Complete year comparison (e.g., 2023-2024)
            $dateRangeInfo = 'Periode: 1 Januari - 31 Desember ' . $previousYear . ' VS 1 Januari - 31 Desember ' . $year;
        } else {
            // Partial year comparison (e.g., 2024-2025 where current year is incomplete)
            $currentEndDateFormatted = date('j F', strtotime($currentYearEndDate));
            $previousEndDateFormatted = date('j F', strtotime($previousYearEndDate));
            $dateRangeInfo = 'Periode: 1 Januari - ' . $previousEndDateFormatted . ' ' . $previousYear . ' VS 1 Januari - ' . $currentEndDateFormatted . ' ' . $year;
        }

        $allBranches = ChartHelper::sortByBranchOrder($allBranches, null);

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
                <div class="title">PENJUALAN TAHUNAN NASIONAL</div>
                <div class="period">Perbandingan Tahun ' . $previousYear . ' vs ' . $year . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
                <div class="period" style="font-size: 9pt; color: #666;">' . htmlspecialchars($dateRangeInfo) . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">NO</th>
                        <th style="width: 150px;">NAMA CABANG</th>
                        <th style="width: 50px;">KODE CABANG</th>
                        <th style="width: 100px; text-align: right;">' . $previousYear . '</th>
                        <th style="width: 100px; text-align: right;">' . $year . '</th>
                        <th style="width: 80px; text-align: right;">GROWTH (%)</th>
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
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Penjualan_Tahunan_Nasional_' . $previousYear . '-' . $year . '_' . str_replace(' ', '_', $category) . '_' . $type . '.pdf';

        return $pdf->download($filename);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
                        INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
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
                        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
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
                        INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
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
                        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                    GROUP BY
                        org.name
                ) AS ss
                GROUP BY
                    ss.cabang
                ORDER BY
                    ss.cabang
            ";

            $results = DB::select($query, [$category, $month, $year, $category, $month, $year]);

            // Sort results by branch order
            $results = ChartHelper::sortByBranchOrder(collect($results), 'cabang')->all();

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

            // Sort branches using ChartHelper branch order
            $branchOrder = ChartHelper::getBranchOrder();
            $sortedBranches = [];
            foreach ($branchOrder as $branch) {
                if (in_array($branch, $allBranches)) {
                    $sortedBranches[] = $branch;
                }
            }
            // Add any branches not in predefined order
            foreach ($allBranches as $branch) {
                if (!in_array($branch, $sortedBranches)) {
                    $sortedBranches[] = $branch;
                }
            }
            $allBranches = $sortedBranches;

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

    public function exportExcel(Request $request)
    {
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');

        // Get actual revenue data
        $actualData = $this->getActualRevenueData($month, $year, $category);

        // Get target data
        $targetData = $this->getTargetData($month, $year, $category);

        // Get all branch names
        $allBranches = array_unique(array_merge(array_keys($actualData), array_keys($targetData)));

        // Sort branches using ChartHelper branch order
        $branchOrder = ChartHelper::getBranchOrder();
        $sortedBranches = [];
        foreach ($branchOrder as $branch) {
            if (in_array($branch, $allBranches)) {
                $sortedBranches[] = $branch;
            }
        }
        // Add any branches not in predefined order
        foreach ($allBranches as $branch) {
            if (!in_array($branch, $sortedBranches)) {
                $sortedBranches[] = $branch;
            }
        }
        $allBranches = $sortedBranches;

        // Calculate totals
        $totalTarget = array_sum($targetData);
        $totalRealization = 0;
        foreach ($actualData as $data) {
            $totalRealization += $data['netto'];
        }
        $totalPercentage = $totalTarget > 0 ? round(($totalRealization / $totalTarget) * 100) : 0;

        // Format month and category for display
        $months = [
            '',
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
        $monthName = $months[$month] ?? 'Unknown';
        $formattedCategory = ucwords(strtolower($category));
        $filename = 'Target_Penjualan_Netto_' . $monthName . '_' . $year . '_' . str_replace(' ', '_', $category) . '.xls';

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
                            <x:Name>Target Penjualan Netto</x:Name>
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
                .col-no { width: 70px; }
                .col-branch { width: 250px; }
                .col-code { width: 160px; }
                .col-target { width: 280px; }
                .col-realization { width: 280px; }
                .col-percentage { width: 260px; }
            </style>
        </head>
        <body>
            <div class="title">TARGET PENJUALAN NETTO</div>
            <div class="period">Periode ' . $monthName . ' ' . $year . ' - ' . $formattedCategory . '</div>
            <br>
            <table>
                <colgroup>
                    <col class="col-no">
                    <col class="col-branch">
                    <col class="col-code">
                    <col class="col-target">
                    <col class="col-realization">
                    <col class="col-percentage">
                </colgroup>
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA CABANG</th>
                        <th>KODE CABANG</th>
                        <th style="text-align: right;">TARGET (RP)</th>
                        <th style="text-align: right;">REALIZATION (RP)</th>
                        <th style="text-align: right;">ACHIEVEMENT (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($allBranches as $branchName) {
            $target = $targetData[$branchName] ?? 0;
            $actual = $actualData[$branchName]['netto'] ?? 0;
            $percentage = $target > 0 ? round(($actual / $target) * 100) : 0;

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($branchName) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branchName)) . '</td>
                <td class="number">' . number_format($target, 2, '.', ',') . '</td>
                <td class="number">' . number_format($actual, 2, '.', ',') . '</td>
                <td class="number">' . $percentage . '%</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalTarget, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalRealization, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . $totalPercentage . '%</strong></td>
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
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $category = $request->input('category', 'MIKA');

        // Get actual revenue data
        $actualData = $this->getActualRevenueData($month, $year, $category);

        // Get target data
        $targetData = $this->getTargetData($month, $year, $category);

        // Get all branch names
        $allBranches = array_unique(array_merge(array_keys($actualData), array_keys($targetData)));

        // Sort branches using ChartHelper branch order
        $branchOrder = ChartHelper::getBranchOrder();
        $sortedBranches = [];
        foreach ($branchOrder as $branch) {
            if (in_array($branch, $allBranches)) {
                $sortedBranches[] = $branch;
            }
        }
        // Add any branches not in predefined order
        foreach ($allBranches as $branch) {
            if (!in_array($branch, $sortedBranches)) {
                $sortedBranches[] = $branch;
            }
        }
        $allBranches = $sortedBranches;

        // Calculate totals
        $totalTarget = array_sum($targetData);
        $totalRealization = 0;
        foreach ($actualData as $data) {
            $totalRealization += $data['netto'];
        }
        $totalPercentage = $totalTarget > 0 ? round(($totalRealization / $totalTarget) * 100) : 0;

        // Format month and category for display
        $months = [
            '',
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
        $monthName = $months[$month] ?? 'Unknown';
        $formattedCategory = ucwords(strtolower($category));
        $filename = 'Target_Penjualan_Netto_' . $monthName . '_' . $year . '_' . str_replace(' ', '_', $category) . '.pdf';

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
                    font-size: 10pt;
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
                    font-size: 10pt;
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
                <div class="title">TARGET PENJUALAN NETTO</div>
                <div class="period">Periode ' . $monthName . ' ' . $year . ' - ' . $formattedCategory . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">NO</th>
                        <th style="width: 150px;">NAMA CABANG</th>
                        <th style="width: 80px;">KODE CABANG</th>
                        <th style="width: 120px; text-align: right;">TARGET (RP)</th>
                        <th style="width: 120px; text-align: right;">REALIZATION (RP)</th>
                        <th style="width: 80px; text-align: right;">ACHIEVEMENT (%)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($allBranches as $branchName) {
            $target = $targetData[$branchName] ?? 0;
            $actual = $actualData[$branchName]['netto'] ?? 0;
            $percentage = $target > 0 ? round(($actual / $target) * 100) : 0;

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($branchName) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branchName)) . '</td>
                <td class="number">' . number_format($target, 2, '.', ',') . '</td>
                <td class="number">' . number_format($actual, 2, '.', ',') . '</td>
                <td class="number">' . $percentage . '%</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($totalTarget, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($totalRealization, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . $totalPercentage . '%</strong></td>
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

        return $pdf->download($filename);
    }
}

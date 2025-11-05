<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategoryItemController extends Controller
{
    public function getData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $type = $request->input('type', 'NETTO');
        $page = (int)$request->input('page', 1);
        $perPage = $page === 1 ? 9 : 8;

        // Base query to get all branches with invoices in the date range for pagination
        // Must filter by document type to match the main query
        $baseQuery = DB::table('c_invoice as h')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate]);

        // Apply document type filter based on revenue type
        if ($type === 'NETTO') {
            $baseQuery->whereRaw('SUBSTR(h.documentno, 1, 3) IN (?, ?)', ['INC', 'CNC']);
        } else {
            $baseQuery->whereRaw('h.documentno LIKE ?', ['INC%']);
        }

        $baseQuery->select('org.name as branch')
            ->groupBy('org.name')
            ->orderBy('org.name');

        $allBranches = $baseQuery->get()->pluck('branch');

        // Calculate offset for pagination with different page sizes
        $offset = $page === 1 ? 0 : 9 + (($page - 2) * 8);
        $paginatedBranches = $allBranches->slice($offset, $perPage)->values();

        if ($paginatedBranches->isEmpty()) {
            return response()->json([
                'chartData' => [
                    'labels' => [],
                    'datasets' => []
                ],
                'pagination' => [
                    'currentPage' => $page,
                    'hasMorePages' => false
                ]
            ]);
        }

        // Main data query based on the provided SQL
        $dataQuery = DB::table('c_invoiceline as d')
            ->join('c_invoice as h', 'd.c_invoice_id', '=', 'h.c_invoice_id')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->join('m_product as prd', 'd.m_product_id', '=', 'prd.m_product_id')
            ->join('m_product_category as cat', 'prd.m_product_category_id', '=', 'cat.m_product_category_id')
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->where('d.qtyinvoiced', '>', 0)
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate])
            ->whereIn('org.name', $paginatedBranches);

        // Apply different calculation based on type
        if ($type === 'NETTO') {
            // Netto: INC adds, CNC subtracts
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(CASE
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'INC\') THEN d.linenetamt
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'CNC\') THEN -d.linenetamt
                    ELSE 0
                END) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('SUBSTR(h.documentno, 1, 3) IN (?, ?)', ['INC', 'CNC']);
        } else {
            // Bruto: simple sum (only INC documents)
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(d.linenetamt) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('h.documentno LIKE ?', ['INC%']);
        }

        $dataQuery->groupBy('org.name', 'cat.name');

        $data = $dataQuery->get();

        // Enforce legend order
        $desiredOrder = ['MIKA', 'SPARE PART', 'CAT', 'PRODUCT IMPORT', 'AKSESORIS'];
        $foundCategories = $data->pluck('category')->unique()->values();
        // Keep only categories present in data, in the desired order
        $categories = collect($desiredOrder)
            ->filter(function ($c) use ($foundCategories) {
                return $foundCategories->contains($c);
            })
            ->values();
        $dataByBranch = $data->groupBy('branch');

        $branchTotals = $paginatedBranches->mapWithKeys(function ($branch) use ($dataByBranch) {
            return [$branch => $dataByBranch->get($branch, collect())->sum('total_revenue')];
        });

        $totalRevenueForPage = $branchTotals->sum();

        $categoryColors = ChartHelper::getCategoryColors();

        $datasets = $categories->map(function ($category) use ($paginatedBranches, $dataByBranch, $branchTotals, $totalRevenueForPage, $categoryColors) {
            $dataPoints = $paginatedBranches->map(function ($branch) use ($category, $dataByBranch, $branchTotals, $totalRevenueForPage) {
                $branchData = $dataByBranch->get($branch, collect());
                $revenue = $branchData->where('category', $category)->sum('total_revenue');
                $totalForBranch = $branchTotals->get($branch, 0);
                return [
                    'x' => $branch,
                    'y' => $totalForBranch > 0 ? ($revenue / $totalForBranch) : 0,
                    'v' => $revenue,
                    'value' => $totalForBranch,
                    'width' => $totalRevenueForPage > 0 ? ($totalForBranch / $totalRevenueForPage) : 0
                ];
            });

            return [
                'label' => $category,
                'data' => $dataPoints,
                'backgroundColor' => $categoryColors[$category] ?? '#c9cbcf',
            ];
        });


        // Map branch names to abbreviations
        $abbreviatedLabels = $paginatedBranches->map(function ($branch) {
            return ChartHelper::getBranchAbbreviation($branch);
        })->toArray();

        // Calculate hasMorePages based on new pagination logic
        $totalProcessed = $page === 1 ? 9 : 9 + (($page - 1) * 8);
        $hasMorePages = $allBranches->count() > $totalProcessed;

        return response()->json([
            'chartData' => [
                'labels' => $abbreviatedLabels,
                'datasets' => $datasets->toArray()
            ],
            'pagination' => [
                'currentPage' => $page,
                'hasMorePages' => $hasMorePages
            ]
        ]);
    }

    public function exportExcel(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $type = $request->input('type', 'BRUTO'); // BRUTO or NETTO

        // Main data query based on the provided SQL
        $dataQuery = DB::table('c_invoiceline as d')
            ->join('c_invoice as h', 'd.c_invoice_id', '=', 'h.c_invoice_id')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->join('m_product as prd', 'd.m_product_id', '=', 'prd.m_product_id')
            ->join('m_product_category as cat', 'prd.m_product_category_id', '=', 'cat.m_product_category_id')
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->where('d.qtyinvoiced', '>', 0)
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate]);

        // Apply different calculation based on type
        if ($type === 'NETTO') {
            // Netto: INC adds, CNC subtracts
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(CASE
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'INC\') THEN d.linenetamt
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'CNC\') THEN -d.linenetamt
                    ELSE 0
                END) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('SUBSTR(h.documentno, 1, 3) IN (?, ?)', ['INC', 'CNC']);
        } else {
            // Bruto: simple sum (only INC documents)
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(d.linenetamt) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('h.documentno LIKE ?', ['INC%']);
        }

        $dataQuery->groupBy('org.name', 'cat.name')
            ->orderBy('org.name');

        $data = $dataQuery->get();

        // Get all unique branches and categories
        $branches = $data->pluck('branch')->unique()->sort()->values();
        $categories = ['MIKA', 'SPARE PART', 'CAT', 'PRODUCT IMPORT', 'AKSESORIS'];

        // Filter categories to only those present in data
        $foundCategories = $data->pluck('category')->unique()->values();
        $categories = collect($categories)->filter(function ($c) use ($foundCategories) {
            return $foundCategories->contains($c);
        })->values();

        // Group data by branch
        $dataByBranch = $data->groupBy('branch');

        // Format dates for filename and display
        $formattedStartDate = Carbon::parse($startDate)->format('d F Y');
        $formattedEndDate = Carbon::parse($endDate)->format('d F Y');
        $fileStartDate = Carbon::parse($startDate)->format('d-m-Y');
        $fileEndDate = Carbon::parse($endDate)->format('d-m-Y');
        $typeLabel = $type === 'NETTO' ? 'Netto' : 'Bruto';
        $filename = 'Kontribusi_Kategori_Barang_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.xls';

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
                            <x:Name>Kontribusi Kategori Barang</x:Name>
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
                .col-no { width: 70px; }
                .col-branch { width: 250px; }
                .col-code { width: 160px; }
                .col-category { width: 300px; }
            </style>
        </head>
        <body>
            <div class="title">Kontribusi Kategori Barang (' . $typeLabel . ')</div>
            <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
            <br>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Cabang</th>
                        <th>Kode Cabang</th>';

        foreach ($categories as $category) {
            $html .= '<th style="text-align: right;">' . htmlspecialchars($category) . ' (Rp)</th>';
        }

        $html .= '
                        <th style="text-align: right;">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $categoryTotals = [];
        foreach ($categories as $category) {
            $categoryTotals[$category] = 0;
        }
        $grandTotal = 0;

        foreach ($branches as $branch) {
            $branchData = $dataByBranch->get($branch, collect());
            $branchTotal = 0;

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($branch) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branch)) . '</td>';

            foreach ($categories as $category) {
                $revenue = $branchData->where('category', $category)->sum('total_revenue');
                $categoryTotals[$category] += $revenue;
                $branchTotal += $revenue;
                $html .= '<td class="number">' . number_format($revenue, 2, '.', ',') . '</td>';
            }

            $grandTotal += $branchTotal;
            $html .= '<td class="number">' . number_format($branchTotal, 2, '.', ',') . '</td>';
            $html .= '</tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>';

        foreach ($categories as $category) {
            $html .= '<td class="number"><strong>' . number_format($categoryTotals[$category], 2, '.', ',') . '</strong></td>';
        }

        $html .= '
                        <td class="number"><strong>' . number_format($grandTotal, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $type = $request->input('type', 'BRUTO'); // BRUTO or NETTO

        // Main data query based on the provided SQL
        $dataQuery = DB::table('c_invoiceline as d')
            ->join('c_invoice as h', 'd.c_invoice_id', '=', 'h.c_invoice_id')
            ->join('ad_org as org', 'h.ad_org_id', '=', 'org.ad_org_id')
            ->join('m_product as prd', 'd.m_product_id', '=', 'prd.m_product_id')
            ->join('m_product_category as cat', 'prd.m_product_category_id', '=', 'cat.m_product_category_id')
            ->where('h.ad_client_id', 1000001)
            ->where('h.issotrx', 'Y')
            ->where('d.qtyinvoiced', '>', 0)
            ->whereIn('h.docstatus', ['CO', 'CL'])
            ->where('h.isactive', 'Y')
            ->whereBetween(DB::raw('DATE(h.dateinvoiced)'), [$startDate, $endDate]);

        // Apply different calculation based on type
        if ($type === 'NETTO') {
            // Netto: INC adds, CNC subtracts
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(CASE
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'INC\') THEN d.linenetamt
                    WHEN SUBSTR(h.documentno, 1, 3) IN (\'CNC\') THEN -d.linenetamt
                    ELSE 0
                END) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('SUBSTR(h.documentno, 1, 3) IN (?, ?)', ['INC', 'CNC']);
        } else {
            // Bruto: simple sum (only INC documents)
            $dataQuery->select(
                'org.name as branch',
                'cat.name as category',
                DB::raw('SUM(d.linenetamt) as total_revenue')
            )
                ->where('d.linenetamt', '>', 0)
                ->whereRaw('h.documentno LIKE ?', ['INC%']);
        }

        $dataQuery->groupBy('org.name', 'cat.name')
            ->orderBy('org.name');

        $data = $dataQuery->get();

        // Get all unique branches and categories
        $branches = $data->pluck('branch')->unique()->sort()->values();
        $categories = ['MIKA', 'SPARE PART', 'CAT', 'PRODUCT IMPORT', 'AKSESORIS'];

        // Filter categories to only those present in data
        $foundCategories = $data->pluck('category')->unique()->values();
        $categories = collect($categories)->filter(function ($c) use ($foundCategories) {
            return $foundCategories->contains($c);
        })->values();

        // Group data by branch
        $dataByBranch = $data->groupBy('branch');

        // Format dates for filename and display
        $formattedStartDate = Carbon::parse($startDate)->format('d F Y');
        $formattedEndDate = Carbon::parse($endDate)->format('d F Y');
        $fileStartDate = Carbon::parse($startDate)->format('d-m-Y');
        $fileEndDate = Carbon::parse($endDate)->format('d-m-Y');
        $typeLabel = $type === 'NETTO' ? 'Netto' : 'Bruto';

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
                <div class="title">Kontribusi Kategori Barang (' . $typeLabel . ')</div>
                <div class="period">Periode ' . $formattedStartDate . ' sampai ' . $formattedEndDate . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th style="width: 120px;">Nama Cabang</th>
                        <th style="width: 50px;">Kode Cabang</th>';

        foreach ($categories as $category) {
            $html .= '<th style="width: 90px; text-align: right;">' . htmlspecialchars($category) . ' (Rp)</th>';
        }

        $html .= '
                        <th style="width: 100px; text-align: right;">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        $categoryTotals = [];
        foreach ($categories as $category) {
            $categoryTotals[$category] = 0;
        }
        $grandTotal = 0;

        foreach ($branches as $branch) {
            $branchData = $dataByBranch->get($branch, collect());
            $branchTotal = 0;

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($branch) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branch)) . '</td>';

            foreach ($categories as $category) {
                $revenue = $branchData->where('category', $category)->sum('total_revenue');
                $categoryTotals[$category] += $revenue;
                $branchTotal += $revenue;
                $html .= '<td class="number">' . number_format($revenue, 2, '.', ',') . '</td>';
            }

            $grandTotal += $branchTotal;
            $html .= '<td class="number">' . number_format($branchTotal, 2, '.', ',') . '</td>';
            $html .= '</tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>';

        foreach ($categories as $category) {
            $html .= '<td class="number"><strong>' . number_format($categoryTotals[$category], 2, '.', ',') . '</strong></td>';
        }

        $html .= '
                        <td class="number"><strong>' . number_format($grandTotal, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Kontribusi_Kategori_Barang_' . $typeLabel . '_' . $fileStartDate . '_sampai_' . $fileEndDate . '.pdf';

        return $pdf->download($filename);
    }
}

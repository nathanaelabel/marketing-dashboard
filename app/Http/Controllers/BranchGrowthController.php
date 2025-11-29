<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ChartHelper;

class BranchGrowthController extends Controller
{
    public function getData(Request $request)
    {
        try {
            $startYear = $request->input('start_year');
            $endYear = $request->input('end_year');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if ($startYear && $endYear) {
                $startDate = $startYear . '-01-01';
                $endDate = $endYear . '-12-31';
            } else {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $startDate = $startDate ?: '2024-01-01';
                $endDate = $endDate ?: $yesterday;
            }

            $category = $request->get('category', 'MIKA');
            $branch = $request->get('branch', 'National');
            $type = $request->get('type', 'NETTO');

            Log::info('BranchGrowthController getData called', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'category' => $category,
                'branch' => $branch,
                'type' => $type
            ]);

            $data = $this->getRevenueDataByDateRange($startDate, $endDate, $category, $branch, $type);

            Log::info('BranchGrowthController getData result', ['count' => count($data)]);

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
                'type' => $request->get('type'),
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

    private function getRevenueDataByDateRange($startDate, $endDate, $category, $branch, $type = 'NETTO')
    {
        try {
            // Tingkatkan batas waktu eksekusi untuk query berat
            set_time_limit(180);

            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 120000");
                }
            } catch (\Exception $e) {
                Log::debug('Could not set statement timeout', ['error' => $e->getMessage()]);
            }

            $branchCondition = '';
            $bindings = [];

            if ($branch !== 'National') {
                $branchCondition = 'AND org.name = ?';
                $bindings = [$branch, $startDate, $endDate, $category, $category];
            } else {
                $bindings = [$startDate, $endDate, $category, $category];
            }

            if ($type === 'BRUTO') {
                // Query Bruto - hanya dokumen INC
                $query = "
                    SELECT
                        EXTRACT(year FROM h.dateinvoiced) as year,
                        EXTRACT(month FROM h.dateinvoiced) as month,
                        COALESCE(SUM(d.linenetamt), 0) as net_revenue
                    FROM
                        c_invoice h
                        INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                        INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                        INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                        INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                        LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                    WHERE
                        h.ad_client_id = 1000001
                        AND h.issotrx = 'Y'
                        AND d.qtyinvoiced > 0
                        AND d.linenetamt > 0
                        AND h.docstatus IN ('CO', 'CL')
                        AND h.isactive = 'Y'
                        {$branchCondition}
                        AND h.dateinvoiced::date BETWEEN ? AND ?
                        AND h.documentno LIKE 'INC%'
                        AND (
                            CASE 
                                WHEN ? = 'MIKA' THEN (
                                    cat.value = 'MIKA' 
                                    OR (
                                        cat.value = 'PRODUCT IMPORT' 
                                        AND prd.name NOT LIKE '%BOHLAM%'
                                        AND psc.value = 'MIKA'
                                    )
                                    OR (
                                        cat.value = 'PRODUCT IMPORT' 
                                        AND (
                                            prd.name LIKE '%FILTER UDARA%'
                                            OR prd.name LIKE '%SWITCH REM%'
                                            OR prd.name LIKE '%DOP RITING%'
                                        )
                                    )
                                )
                                ELSE cat.name = ?
                            END
                        )
                        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                    GROUP BY
                        EXTRACT(year FROM h.dateinvoiced),
                        EXTRACT(month FROM h.dateinvoiced)
                    ORDER BY
                        year, month
                ";
            } else {
                // Query Netto - INC dikurangi CNC
                $query = "
                    SELECT
                        EXTRACT(year FROM h.dateinvoiced) as year,
                        EXTRACT(month FROM h.dateinvoiced) as month,
                        COALESCE(SUM(CASE
                            WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.linenetamt
                            WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.linenetamt
                        END), 0) as net_revenue
                    FROM
                        c_invoice h
                        INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                        INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                        INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                        INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                        INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                        LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                    WHERE
                        h.ad_client_id = 1000001
                        AND h.issotrx = 'Y'
                        AND d.qtyinvoiced > 0
                        AND d.linenetamt > 0
                        AND h.docstatus IN ('CO', 'CL')
                        AND h.isactive = 'Y'
                        {$branchCondition}
                        AND h.dateinvoiced::date BETWEEN ? AND ?
                        AND SUBSTR(h.documentno, 1, 3) IN ('INC', 'CNC')
                        AND (
                            CASE 
                                WHEN ? = 'MIKA' THEN (
                                    cat.value = 'MIKA' 
                                    OR (
                                        cat.value = 'PRODUCT IMPORT' 
                                        AND prd.name NOT LIKE '%BOHLAM%'
                                        AND psc.value = 'MIKA'
                                    )
                                    OR (
                                        cat.value = 'PRODUCT IMPORT' 
                                        AND (
                                            prd.name LIKE '%FILTER UDARA%'
                                            OR prd.name LIKE '%SWITCH REM%'
                                            OR prd.name LIKE '%DOP RITING%'
                                        )
                                    )
                                )
                                ELSE cat.name = ?
                            END
                        )
                        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                    GROUP BY
                        EXTRACT(year FROM h.dateinvoiced),
                        EXTRACT(month FROM h.dateinvoiced)
                    ORDER BY
                        year, month
                ";
            }

            $result = DB::select($query, $bindings);

            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 0");
                }
            } catch (\Exception $e) {
            }

            return $result;
        } catch (\Exception $e) {
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SET statement_timeout = 0");
                }
            } catch (\Exception $resetError) {
            }

            Log::error('Error fetching revenue data by date range', [
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

    private function formatMonthlyGrowthData($data, $startDate, $endDate)
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $startYear = (int)$start->format('Y');
            $endYear = (int)$end->format('Y');

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

            $yearlyData = [];
            foreach ($data as $row) {
                $year = (int)$row->year;
                $month = (int)$row->month;
                if (!isset($yearlyData[$year])) {
                    $yearlyData[$year] = array_fill(1, 12, 0);
                }
                $yearlyData[$year][$month] = (float)$row->net_revenue;
            }

            $datasets = [];
            $defaultColors = [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)'
            ];
            $colorIndex = 0;
            $allValues = [];

            for ($year = $startYear; $year <= $endYear; $year++) {
                $monthlyValues = [];

                for ($month = 1; $month <= 12; $month++) {
                    $value = isset($yearlyData[$year][$month]) ? $yearlyData[$year][$month] : 0;
                    $monthlyValues[] = $value;
                    if ($value > 0) {
                        $allValues[] = $value;
                    }
                }

                $color = $defaultColors[$colorIndex % count($defaultColors)];

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

            $maxValue = !empty($allValues) ? max($allValues) : 100;

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
            $branchCondition = '';
            $branchBinding = [];
            if ($branch !== 'National') {
                $branchCondition = 'AND org.name = ?';
                $branchBinding[] = $branch;
            }

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
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    h.issotrx = 'Y'
                    AND h.ad_client_id = 1000001
                    AND h.docstatus IN ('CO', 'CL')
                    AND (h.documentno LIKE 'INC%' OR h.documentno LIKE 'CNC%')
                    AND (
                        CASE 
                            WHEN ? = 'MIKA' THEN (
                                cat.value = 'MIKA' 
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            ELSE cat.name = ?
                        END
                    )
                    AND h.dateinvoiced >= ? AND h.dateinvoiced < ?
                    {$branchCondition}
            ";

            $startDate = $startYear . '-01-01';
            $endDate = ($endYear + 1) . '-01-01';

            $bindings = array_merge([$category, $category, $startDate, $endDate], $branchBinding);

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
        $type = $request->input('type', 'NETTO');
        $currentYear = date('Y');

        $startDate = $startYear . '-01-01';
        $endDate = $endYear . '-12-31';

        $branches = ChartHelper::getLocations();

        if ($type === 'BRUTO') {
            // Query Bruto - hanya dokumen INC
            $query = "
                SELECT
                    org.name AS branch_name,
                    EXTRACT(month FROM h.dateinvoiced) AS month_number,
                    EXTRACT(year FROM h.dateinvoiced) AS year_number,
                    COALESCE(SUM(d.linenetamt), 0) as net_revenue
                FROM
                    c_invoice h
                    INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    h.ad_client_id = 1000001
                    AND h.issotrx = 'Y'
                    AND d.qtyinvoiced > 0
                    AND d.linenetamt > 0
                    AND h.docstatus IN ('CO', 'CL')
                    AND h.isactive = 'Y'
                    AND h.dateinvoiced::date BETWEEN ? AND ?
                    AND h.documentno LIKE 'INC%'
                    AND (
                        CASE 
                            WHEN ? = 'MIKA' THEN (
                                cat.value = 'MIKA' 
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            ELSE cat.name = ?
                        END
                    )
                    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                GROUP BY
                    org.name, EXTRACT(month FROM h.dateinvoiced), EXTRACT(year FROM h.dateinvoiced)
                ORDER BY
                    org.name, year_number, month_number
            ";
        } else {
            // Query Netto - INC dikurangi CNC
            $query = "
                SELECT
                    org.name AS branch_name,
                    EXTRACT(month FROM h.dateinvoiced) AS month_number,
                    EXTRACT(year FROM h.dateinvoiced) AS year_number,
                    COALESCE(SUM(CASE
                        WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.linenetamt
                        WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.linenetamt
                    END), 0) as net_revenue
                FROM
                    c_invoice h
                    INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    h.ad_client_id = 1000001
                    AND h.issotrx = 'Y'
                    AND d.qtyinvoiced > 0
                    AND d.linenetamt > 0
                    AND h.docstatus IN ('CO', 'CL')
                    AND h.isactive = 'Y'
                    AND h.dateinvoiced::date BETWEEN ? AND ?
                    AND SUBSTR(h.documentno, 1, 3) IN ('INC', 'CNC')
                    AND (
                        CASE 
                            WHEN ? = 'MIKA' THEN (
                                cat.value = 'MIKA' 
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            ELSE cat.name = ?
                        END
                    )
                    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                GROUP BY
                    org.name, EXTRACT(month FROM h.dateinvoiced), EXTRACT(year FROM h.dateinvoiced)
                ORDER BY
                    org.name, year_number, month_number
            ";
        }

        $allData = DB::select($query, [$startDate, $endDate, $category, $category]);

        $allData = ChartHelper::sortByBranchOrder(collect($allData), 'branch_name')->all();

        $lastAvailableMonth = 0;
        foreach ($allData as $row) {
            if ((int)$row->year_number == $endYear) {
                $lastAvailableMonth = max($lastAvailableMonth, (int)$row->month_number);
            }
        }

        if ($lastAvailableMonth == 0) {
            $lastAvailableMonth = 12;
        }

        $monthsToShow = 12;
        if ($endYear == $currentYear && $lastAvailableMonth < 12) {
            $monthsToShow = $lastAvailableMonth;
        }

        $allBranchesData = [];
        foreach ($branches as $branch) {
            $branchData = [
                'branch' => $branch,
                'code' => ChartHelper::getBranchAbbreviation($branch),
                'years' => []
            ];

            for ($year = $startYear; $year <= $endYear; $year++) {
                $branchData['years'][$year] = array_fill(1, 12, 0);
            }

            foreach ($allData as $row) {
                if ($row->branch_name === $branch) {
                    $month = (int)$row->month_number;
                    $year = (int)$row->year_number;
                    if ($year >= $startYear && $year <= $endYear) {
                        $branchData['years'][$year][$month] = (float)$row->net_revenue;
                    }
                }
            }

            $allBranchesData[] = $branchData;
        }

        $filename = 'Pertumbuhan_Penjualan_Cabang_' . $startYear . '-' . $endYear . '_' . str_replace(' ', '_', $category) . '_' . $type . '.xls';

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
                            <x:Name>Pertumbuhan Penjualan Cabang</x:Name>
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
                table {
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
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
                    text-align: center;
                }
                .branch-title {
                    font-family: Verdana, sans-serif;
                    font-size: 12pt;
                    font-weight: bold;
                    margin-top: 15px;
                    margin-bottom: 5px;
                    text-align: center;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #E8E8E8;
                }
                .number { text-align: right; }
                .year-header {
                    font-family: Verdana, sans-serif;
                    font-size: 10pt;
                    text-align: center;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="title">PERTUMBUHAN PENJUALAN CABANG ' . $startYear . ' - ' . $endYear . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>
            <br>';

        $nationalData = [
            'branch' => 'NATIONAL',
            'code' => 'NATIONAL',
            'years' => []
        ];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $nationalData['years'][$year] = array_fill(1, 12, 0);
        }
        foreach ($allBranchesData as $branchData) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    $nationalData['years'][$year][$month] += $branchData['years'][$year][$month];
                }
            }
        }

        $renderBranchTable = function ($branchData, $branchName, $addSpacing = true) use ($startYear, $endYear, $monthsToShow, $monthLabels) {
            $tableHtml = '';
            if ($addSpacing) {
                $tableHtml .= '<br>';
            }
            $tableHtml .= '<div class="branch-title">CABANG ' . strtoupper($branchName) . '</div>';
            $tableHtml .= '<table>
                <thead>
                    <tr>
                        <th>TH / BLN</th>';

            for ($month = 1; $month <= $monthsToShow; $month++) {
                $tableHtml .= '<th>' . strtoupper($monthLabels[$month - 1]) . '</th>';
            }

            $tableHtml .= '<th>TOTAL</th>
                        <th>AVERAGE</th>
                        <th>% GROWTH</th>
                    </tr>
                </thead>
                <tbody>';

            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableHtml .= '<tr>
                    <td class="year-header">' . $year . '</td>';

                $yearTotal = 0;
                for ($month = 1; $month <= $monthsToShow; $month++) {
                    $value = $branchData['years'][$year][$month];
                    $yearTotal += $value;
                    $tableHtml .= '<td class="number">' . number_format($value, 0, '.', ',') . '</td>';
                }

                $tableHtml .= '<td class="number"><strong>' . number_format($yearTotal, 0, '.', ',') . '</strong></td>';

                $average = $monthsToShow > 0 ? $yearTotal / $monthsToShow : 0;
                $tableHtml .= '<td class="number">' . number_format($average, 0, '.', ',') . '</td>';

                $growth = 0;
                if ($year > $startYear) {
                    $prevYear = $year - 1;
                    $prevYearTotal = 0;
                    for ($month = 1; $month <= $monthsToShow; $month++) {
                        $prevYearTotal += $branchData['years'][$prevYear][$month];
                    }
                    if ($prevYearTotal > 0) {
                        $growth = (($yearTotal - $prevYearTotal) / $prevYearTotal) * 100;
                    }
                }
                $tableHtml .= '<td class="number">' . number_format($growth, 2, '.', ',') . '</td>';

                $tableHtml .= '</tr>';
            }

            $tableHtml .= '</tbody>
            </table>';

            return $tableHtml;
        };

        $isFirst = true;
        foreach ($allBranchesData as $branchData) {
            $branchName = ChartHelper::getBranchDisplayName($branchData['branch']);
            $html .= $renderBranchTable($branchData, $branchName, !$isFirst);
            $isFirst = false;
        }

        $html .= $renderBranchTable($nationalData, 'NASIONAL', true);

        $html .= '<div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic; margin-top: 10px;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $startYear = $request->input('start_year', 2024);
        $endYear = $request->input('end_year', 2025);
        $category = $request->input('category', 'MIKA');
        $type = $request->input('type', 'NETTO');
        $currentYear = date('Y');

        $startDate = $startYear . '-01-01';
        $endDate = $endYear . '-12-31';

        $branches = ChartHelper::getLocations();

        if ($type === 'BRUTO') {
            // Query Bruto - hanya dokumen INC
            $query = "
                SELECT
                    org.name AS branch_name,
                    EXTRACT(month FROM h.dateinvoiced) AS month_number,
                    EXTRACT(year FROM h.dateinvoiced) AS year_number,
                    COALESCE(SUM(d.linenetamt), 0) as net_revenue
                FROM
                    c_invoice h
                    INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    h.ad_client_id = 1000001
                    AND h.issotrx = 'Y'
                    AND d.qtyinvoiced > 0
                    AND d.linenetamt > 0
                    AND h.docstatus IN ('CO', 'CL')
                    AND h.isactive = 'Y'
                    AND h.dateinvoiced::date BETWEEN ? AND ?
                    AND h.documentno LIKE 'INC%'
                    AND (
                        CASE 
                            WHEN ? = 'MIKA' THEN (
                                cat.value = 'MIKA' 
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            ELSE cat.name = ?
                        END
                    )
                    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                GROUP BY
                    org.name, EXTRACT(month FROM h.dateinvoiced), EXTRACT(year FROM h.dateinvoiced)
                ORDER BY
                    org.name, year_number, month_number
            ";
        } else {
            // Query Netto - INC dikurangi CNC
            $query = "
                SELECT
                    org.name AS branch_name,
                    EXTRACT(month FROM h.dateinvoiced) AS month_number,
                    EXTRACT(year FROM h.dateinvoiced) AS year_number,
                    COALESCE(SUM(CASE
                        WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.linenetamt
                        WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.linenetamt
                    END), 0) as net_revenue
                FROM
                    c_invoice h
                    INNER JOIN c_invoiceline d ON h.c_invoice_id = d.c_invoice_id
                    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                    INNER JOIN c_bpartner cust ON h.c_bpartner_id = cust.c_bpartner_id
                    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
                    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
                WHERE
                    h.ad_client_id = 1000001
                    AND h.issotrx = 'Y'
                    AND d.qtyinvoiced > 0
                    AND d.linenetamt > 0
                    AND h.docstatus IN ('CO', 'CL')
                    AND h.isactive = 'Y'
                    AND h.dateinvoiced::date BETWEEN ? AND ?
                    AND SUBSTR(h.documentno, 1, 3) IN ('INC', 'CNC')
                    AND (
                        CASE 
                            WHEN ? = 'MIKA' THEN (
                                cat.value = 'MIKA' 
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND prd.name NOT LIKE '%BOHLAM%'
                                    AND psc.value = 'MIKA'
                                )
                                OR (
                                    cat.value = 'PRODUCT IMPORT' 
                                    AND (
                                        prd.name LIKE '%FILTER UDARA%'
                                        OR prd.name LIKE '%SWITCH REM%'
                                        OR prd.name LIKE '%DOP RITING%'
                                    )
                                )
                            )
                            ELSE cat.name = ?
                        END
                    )
                    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
                GROUP BY
                    org.name, EXTRACT(month FROM h.dateinvoiced), EXTRACT(year FROM h.dateinvoiced)
                ORDER BY
                    org.name, year_number, month_number
            ";
        }

        $allData = DB::select($query, [$startDate, $endDate, $category, $category]);

        // Sort data by branch order
        $allData = ChartHelper::sortByBranchOrder(collect($allData), 'branch_name')->all();

        // Detect last available month in end year data
        $lastAvailableMonth = 0;
        foreach ($allData as $row) {
            if ((int)$row->year_number == $endYear) {
                $lastAvailableMonth = max($lastAvailableMonth, (int)$row->month_number);
            }
        }

        // If no data for end year, default to 12 (full year)
        if ($lastAvailableMonth == 0) {
            $lastAvailableMonth = 12;
        }

        // Determine months to show
        $monthsToShow = 12;
        if ($endYear == $currentYear && $lastAvailableMonth < 12) {
            $monthsToShow = $lastAvailableMonth;
        }

        $allBranchesData = [];
        foreach ($branches as $branch) {
            $branchData = [
                'branch' => $branch,
                'code' => ChartHelper::getBranchAbbreviation($branch),
                'years' => []
            ];

            for ($year = $startYear; $year <= $endYear; $year++) {
                $branchData['years'][$year] = array_fill(1, 12, 0);
            }

            foreach ($allData as $row) {
                if ($row->branch_name === $branch) {
                    $month = (int)$row->month_number;
                    $year = (int)$row->year_number;
                    if ($year >= $startYear && $year <= $endYear) {
                        $branchData['years'][$year][$month] = (float)$row->net_revenue;
                    }
                }
            }

            $allBranchesData[] = $branchData;
        }

        $monthLabels = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        // Create HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 15px; }
                body {
                    font-family: Verdana, sans-serif;
                    font-size: 7pt;
                    margin: 0;
                    padding: 10px;
                }
                .title {
                    font-size: 14pt;
                    font-weight: bold;
                    text-align: center;
                    margin-bottom: 15px;
                }
                .branch-title {
                    font-size: 10pt;
                    font-weight: bold;
                    text-align: center;
                    margin-top: 10px;
                    margin-bottom: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 15px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 4px;
                    text-align: left;
                    font-size: 7pt;
                }
                th {
                    background-color: #F5F5F5;
                    color: #000;
                    font-weight: bold;
                    text-align: center;
                }
                .number { text-align: right; }
                .year-header {
                    font-weight: bold;
                    text-align: center;
                }
                .footer {
                    font-size: 6pt;
                    font-style: italic;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="title">PERTUMBUHAN PENJUALAN CABANG ' . $startYear . ' - ' . $endYear . ' | Kategori ' . htmlspecialchars($category) . ' | Tipe ' . htmlspecialchars($type) . '</div>';

        // Calculate NATIONAL totals first
        $nationalData = [
            'branch' => 'NATIONAL',
            'code' => 'NATIONAL',
            'years' => []
        ];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $nationalData['years'][$year] = array_fill(1, 12, 0);
        }
        foreach ($allBranchesData as $branchData) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    $nationalData['years'][$year][$month] += $branchData['years'][$year][$month];
                }
            }
        }

        // Function to render a branch table
        $renderBranchTable = function ($branchData, $branchName) use ($startYear, $endYear, $monthsToShow, $monthLabels) {
            $tableHtml = '<div class="branch-title">CABANG ' . strtoupper($branchName) . '</div>';
            $tableHtml .= '<table>
                <thead>
                    <tr>
                        <th>TH / BLN</th>';

            // Month headers (only up to monthsToShow)
            for ($month = 1; $month <= $monthsToShow; $month++) {
                $tableHtml .= '<th>' . strtoupper($monthLabels[$month - 1]) . '</th>';
            }

            $tableHtml .= '<th>TOTAL</th>
                        <th>AVERAGE</th>
                        <th>% GROWTH</th>
                    </tr>
                </thead>
                <tbody>';

            // Data rows for each year
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableHtml .= '<tr>
                    <td class="year-header">' . $year . '</td>';

                $yearTotal = 0;
                for ($month = 1; $month <= $monthsToShow; $month++) {
                    $value = $branchData['years'][$year][$month];
                    $yearTotal += $value;
                    $tableHtml .= '<td class="number">' . number_format($value, 0, '.', ',') . '</td>';
                }

                // TOTAL
                $tableHtml .= '<td class="number"><strong>' . number_format($yearTotal, 0, '.', ',') . '</strong></td>';

                // AVERAGE
                $average = $monthsToShow > 0 ? $yearTotal / $monthsToShow : 0;
                $tableHtml .= '<td class="number">' . number_format($average, 0, '.', ',') . '</td>';

                // % GROWTH (compared to previous year)
                $growth = 0;
                if ($year > $startYear) {
                    $prevYear = $year - 1;
                    $prevYearTotal = 0;
                    for ($month = 1; $month <= $monthsToShow; $month++) {
                        $prevYearTotal += $branchData['years'][$prevYear][$month];
                    }
                    if ($prevYearTotal > 0) {
                        $growth = (($yearTotal - $prevYearTotal) / $prevYearTotal) * 100;
                    }
                }
                $tableHtml .= '<td class="number">' . number_format($growth, 2, '.', ',') . '</td>';

                $tableHtml .= '</tr>';
            }

            $tableHtml .= '</tbody>
            </table>';

            return $tableHtml;
        };

        // Render table for each branch
        foreach ($allBranchesData as $branchData) {
            $branchName = ChartHelper::getBranchDisplayName($branchData['branch']);
            $html .= $renderBranchTable($branchData, $branchName);
        }

        // Render NATIONAL table at the end
        $html .= $renderBranchTable($nationalData, 'NASIONAL');

        // Footer
        $html .= '<div class="footer">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Pertumbuhan_Penjualan_Cabang_' . $startYear . '-' . $endYear . '_' . str_replace(' ', '_', $category) . '_' . $type . '.pdf';

        return $pdf->download($filename);
    }
}

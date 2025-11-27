<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class TableHelper
{
    // Mapping cabang standar untuk cabang PWM
    public static function getBranchMapping()
    {
        return [
            'MPM Tangerang' => 'TGR',
            'PWM Bekasi' => 'BKS',
            'PWM Jakarta' => 'JKT',
            'PWM Pontianak' => 'PTK',
            'PWM Lampung' => 'LMP',
            'PWM Banjarmasin' => 'BJM',
            'PWM Cirebon' => 'CRB',
            'PWM Bandung' => 'BDG',
            'PWM Makassar' => 'MKS',
            'PWM Surabaya' => 'SBY',
            'PWM Semarang' => 'SMG',
            'PWM Purwokerto' => 'PWT',
            'PWM Denpasar' => 'DPS',
            'PWM Palembang' => 'PLB',
            'PWM Padang' => 'PDG',
            'PWM Medan' => 'MDN',
            'PWM Pekanbaru' => 'PKU'
        ];
    }

    // Ambil daftar kode cabang yang terurut
    public static function getBranchCodes()
    {
        return ['TGR', 'BKS', 'JKT', 'PTK', 'LMP', 'BJM', 'CRB', 'BDG', 'MKS', 'SBY', 'SMG', 'PWT', 'DPS', 'PLB', 'PDG', 'MDN', 'PKU'];
    }

    // Validasi parameter bulan dan tahun
    public static function validatePeriodParameters($month, $year)
    {
        $errors = [];

        if (!is_numeric($month) || $month < 1 || $month > 12) {
            $errors[] = 'Invalid month parameter';
        }

        if (!is_numeric($year) || $year < 2020 || $year > 2030) {
            $errors[] = 'Invalid year parameter';
        }

        return $errors;
    }

    // Hitung metadata pagination
    public static function calculatePagination($currentPage, $perPage, $totalCount)
    {
        $totalPages = ceil($totalCount / $perPage);

        return [
            'current_page' => (int)$currentPage,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages,
            'has_next' => $currentPage < $totalPages,
            'has_prev' => $currentPage > 1
        ];
    }

    // Format informasi periode dengan rentang tanggal
    public static function formatPeriodInfo($month, $year)
    {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $monthNameId = [
            'January' => 'Januari',
            'February' => 'Februari',
            'March' => 'Maret',
            'April' => 'April',
            'May' => 'Mei',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'Agustus',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Desember'
        ];

        // Ambil hari terakhir bulan
        $lastDayOfMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

        // Cek apakah ini bulan dan tahun berjalan
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        $yesterday = (int)date('j') - 1; // H-1 (yesterday)

        if ($month == $currentMonth && $year == $currentYear) {
            // Untuk bulan berjalan, tampilkan 1 sampai kemarin
            $endDay = $yesterday > 0 ? $yesterday : 1;
            $dateRange = "01-{$endDay} {$monthNameId[$monthName]} {$year}";
        } else {
            // Untuk bulan lalu, tampilkan rentang bulan penuh
            $dateRange = "01-{$lastDayOfMonth} {$monthNameId[$monthName]} {$year}";
        }

        return [
            'month' => (int)$month,
            'year' => (int)$year,
            'month_name' => $monthName,
            'month_name_id' => $monthNameId[$monthName],
            'date_range' => $dateRange
        ];
    }

    // Format response sukses standar
    public static function successResponse($data, $pagination, $period, $additionalData = [])
    {
        return array_merge([
            'data' => $data,
            'pagination' => $pagination,
            'period' => $period
        ], $additionalData);
    }

    // Format response error standar
    public static function errorResponse($message = 'An error occurred while retrieving data. Please try again.', $statusCode = 500)
    {
        return response()->json([
            'error' => 'Failed to fetch data',
            'message' => $message
        ], $statusCode);
    }

    // Log error dengan format standar
    public static function logError($controllerName, $method, $exception, $context = [])
    {
        Log::error("{$controllerName} {$method} error: " . $exception->getMessage(), array_merge([
            'trace' => $exception->getTraceAsString()
        ], $context));
    }

    // Transform data mentah dengan mengelompokkan dan mengagregasi nilai cabang
    public static function transformDataForBranchTable($rawData, $keyField, $valueField, $additionalFields = [])
    {
        $branchMapping = self::getBranchMapping();
        $branchCodes = self::getBranchCodes();

        // Kelompokkan data berdasarkan key
        $groupedData = [];
        $nationalTotals = [];

        foreach ($rawData as $row) {
            // Buat key unik termasuk field tambahan
            $keyParts = [$row->$keyField];
            foreach ($additionalFields as $field) {
                $keyParts[] = $row->$field ?? '';
            }
            $itemKey = implode('|', $keyParts);

            $branchAbbr = $branchMapping[$row->branch_name] ?? substr($row->branch_name, 0, 3);

            if (!isset($groupedData[$itemKey])) {
                $groupedData[$itemKey] = [
                    $keyField => $row->$keyField,
                    'branches' => []
                ];

                // Tambahkan field tambahan ke data yang dikelompokkan
                foreach ($additionalFields as $field) {
                    $groupedData[$itemKey][$field] = $row->$field ?? '';
                }
            }

            $groupedData[$itemKey]['branches'][$branchAbbr] = (float)($row->$valueField ?? 0);

            // Hitung total nasional
            if (!isset($nationalTotals[$itemKey])) {
                $nationalTotals[$itemKey] = 0;
            }
            $nationalTotals[$itemKey] += (float)($row->$valueField ?? 0);
        }

        return self::formatTableData($groupedData, $nationalTotals, $keyField, $branchCodes, $additionalFields);
    }

    // Format data yang dikelompokkan ke struktur tabel final
    private static function formatTableData($groupedData, $nationalTotals, $keyField, $branchCodes, $additionalFields)
    {
        $tableData = [];
        $no = 1;

        foreach ($groupedData as $itemKey => $itemData) {
            $rowData = [
                'no' => $no++,
            ];

            // Tambahkan field utama
            $rowData[$keyField] = $itemData[$keyField];

            // Add additional fields
            foreach ($additionalFields as $field) {
                $rowData[$field] = $itemData[$field];
            }

            // Tambahkan data cabang
            foreach ($branchCodes as $branchCode) {
                $rowData[strtolower($branchCode)] = $itemData['branches'][$branchCode] ?? 0;
            }

            // Tambahkan total nasional
            $rowData['nasional'] = $nationalTotals[$itemKey];

            $tableData[] = $rowData;
        }

        return $tableData;
    }

    // Bangun query penjualan dasar dengan kondisi umum
    public static function buildBaseSalesQuery($selectFields, $additionalJoins = '', $additionalConditions = '', $groupBy = '', $orderBy = '')
    {
        $baseQuery = "
            SELECT {$selectFields}
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            {$additionalJoins}
            WHERE h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
                {$additionalConditions}
        ";

        if ($groupBy) {
            $baseQuery .= " GROUP BY {$groupBy}";
        }

        if ($orderBy) {
            $baseQuery .= " ORDER BY {$orderBy}";
        }

        return $baseQuery;
    }

    // Kalkulasi nilai standar untuk dokumen INC/CNC
    public static function getValueCalculation($field)
    {
        return "SUM(CASE
            WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.{$field}
            WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.{$field}
            ELSE 0
        END)";
    }

    // Bangun query count untuk pagination
    public static function buildCountQuery($countField, $additionalJoins = '', $additionalConditions = '')
    {
        return "
            SELECT COUNT(DISTINCT {$countField}) as total_count
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            {$additionalJoins}
            WHERE h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
                {$additionalConditions}
        ";
    }
}

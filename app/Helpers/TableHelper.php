<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class TableHelper
{
    /**
     * Standard branch mapping for PWM branches
     */
    public static function getBranchMapping()
    {
        return [
            'PWM Pontianak' => 'PTK',
            'PWM Medan' => 'MDN',
            'PWM Makassar' => 'MKS',
            'PWM Palembang' => 'PLB',
            'PWM Denpasar' => 'DPS',
            'PWM Surabaya' => 'SBY',
            'PWM Pekanbaru' => 'PKU',
            'PWM Cirebon' => 'CRB',
            'MPM Tangerang' => 'TGR',
            'PWM Bekasi' => 'BKS',
            'PWM Semarang' => 'SMG',
            'PWM Banjarmasin' => 'BJM',
            'PWM Bandung' => 'BDG',
            'PWM Lampung' => 'LMP',
            'PWM Jakarta' => 'JKT',
            'PWM Purwokerto' => 'PWT',
            'PWM Padang' => 'PDG'
        ];
    }

    /**
     * Get ordered list of branch codes
     */
    public static function getBranchCodes()
    {
        return ['MDN', 'MKS', 'PLB', 'DPS', 'SBY', 'PKU', 'CRB', 'TGR', 'BKS', 'SMG', 'BJM', 'BDG', 'LMP', 'JKT', 'PTK', 'PWT', 'PDG'];
    }

    /**
     * Validate month and year parameters
     */
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

    /**
     * Calculate pagination metadata
     */
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

    /**
     * Format period information
     */
    public static function formatPeriodInfo($month, $year)
    {
        return [
            'month' => (int)$month,
            'year' => (int)$year,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1))
        ];
    }

    /**
     * Standard success response format
     */
    public static function successResponse($data, $pagination, $period, $additionalData = [])
    {
        return array_merge([
            'data' => $data,
            'pagination' => $pagination,
            'period' => $period
        ], $additionalData);
    }

    /**
     * Standard error response format
     */
    public static function errorResponse($message = 'An error occurred while retrieving data. Please try again.', $statusCode = 500)
    {
        return response()->json([
            'error' => 'Failed to fetch data',
            'message' => $message
        ], $statusCode);
    }

    /**
     * Log error with standard format
     */
    public static function logError($controllerName, $method, $exception, $context = [])
    {
        Log::error("{$controllerName} {$method} error: " . $exception->getMessage(), array_merge([
            'trace' => $exception->getTraceAsString()
        ], $context));
    }

    /**
     * Transform raw data by grouping and aggregating branch values
     * 
     * @param array $rawData - Raw query results
     * @param string $keyField - Field to group by (e.g., 'product_name', 'family_name')
     * @param string $valueField - Field containing the numeric value
     * @param array $additionalFields - Additional fields to include in grouping
     */
    public static function transformDataForBranchTable($rawData, $keyField, $valueField, $additionalFields = [])
    {
        $branchMapping = self::getBranchMapping();
        $branchCodes = self::getBranchCodes();
        
        // Group data by key
        $groupedData = [];
        $nationalTotals = [];

        foreach ($rawData as $row) {
            // Create unique key including additional fields
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
                
                // Add additional fields to the grouped data
                foreach ($additionalFields as $field) {
                    $groupedData[$itemKey][$field] = $row->$field ?? '';
                }
            }

            $groupedData[$itemKey]['branches'][$branchAbbr] = (float)($row->$valueField ?? 0);
            
            // Calculate national total
            if (!isset($nationalTotals[$itemKey])) {
                $nationalTotals[$itemKey] = 0;
            }
            $nationalTotals[$itemKey] += (float)($row->$valueField ?? 0);
        }

        return self::formatTableData($groupedData, $nationalTotals, $keyField, $branchCodes, $additionalFields);
    }

    /**
     * Format grouped data into final table structure
     */
    private static function formatTableData($groupedData, $nationalTotals, $keyField, $branchCodes, $additionalFields)
    {
        $tableData = [];
        $no = 1;

        foreach ($groupedData as $itemKey => $itemData) {
            $rowData = [
                'no' => $no++,
            ];

            // Add main field
            $rowData[$keyField] = $itemData[$keyField];
            
            // Add additional fields
            foreach ($additionalFields as $field) {
                $rowData[$field] = $itemData[$field];
            }

            // Add branch data
            foreach ($branchCodes as $branchCode) {
                $rowData[strtolower($branchCode)] = $itemData['branches'][$branchCode] ?? 0;
            }

            // Add national total
            $rowData['nasional'] = $nationalTotals[$itemKey];

            $tableData[] = $rowData;
        }

        return $tableData;
    }

    /**
     * Build base sales query with common conditions
     */
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

    /**
     * Get standard value calculation for INC/CNC documents
     * Can be used for both amount (linenetamt) and quantity (qtyinvoiced)
     */
    public static function getValueCalculation($field)
    {
        return "SUM(CASE
            WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.{$field}
            WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.{$field}
            ELSE 0
        END)";
    }

    /**
     * Build count query for pagination
     */
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\ChartHelper;

class SalesItemController extends Controller
{
    public function index()
    {
        return view('sales-item');
    }

    public function getData(Request $request)
    {
        try {
            $month = $request->get('month', date('n')); // Current month as default
            $year = $request->get('year', date('Y')); // Current year as default
            $page = $request->get('page', 1);
            $perPage = 50;
            $offset = ($page - 1) * $perPage;

            // Validate month and year
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                return response()->json(['error' => 'Invalid month parameter'], 400);
            }
            if (!is_numeric($year) || $year < 2020 || $year > 2030) {
                return response()->json(['error' => 'Invalid year parameter'], 400);
            }

            // Get all branch data
            $branchData = $this->getSalesItemData($month, $year, $offset, $perPage);
            
            // Get total count for pagination
            $totalCount = $this->getTotalItemCount($month, $year);
            $totalPages = ceil($totalCount / $perPage);

            // Transform data to include all branches in columns
            $transformedData = $this->transformDataForTable($branchData);

            return response()->json([
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'period' => [
                    'month' => (int)$month,
                    'year' => (int)$year,
                    'month_name' => date('F', mktime(0, 0, 0, $month, 1))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('SalesItemController getData error: ' . $e->getMessage(), [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'page' => $request->get('page'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to fetch sales item data',
                'message' => 'An error occurred while retrieving data. Please try again.'
            ], 500);
        }
    }

    private function getSalesItemData($month, $year, $offset, $perPage)
    {
        $query = "
            SELECT  
                org.name as branch_name, 
                prd.name as product_name, 
                prd.status as product_status,
                SUM(CASE
                    WHEN SUBSTR(h.documentno, 1, 3) IN ('INC') THEN d.linenetamt
                    WHEN SUBSTR(h.documentno, 1, 3) IN ('CNC') THEN -d.linenetamt
                    ELSE 0
                END) AS total_net
            FROM
                c_invoiceline d
                INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE  
                h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND d.linenetamt > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
            GROUP BY
                org.name, prd.name, prd.status
            ORDER BY
                prd.name
            LIMIT ? OFFSET ?
        ";

        return DB::select($query, [$month, $year, $perPage, $offset]);
    }

    private function getTotalItemCount($month, $year)
    {
        $query = "
            SELECT COUNT(DISTINCT CONCAT(prd.name, '|', prd.status)) as total_count
            FROM
                c_invoiceline d
                INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE  
                h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND d.linenetamt > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
        ";

        $result = DB::select($query, [$month, $year]);
        return $result[0]->total_count ?? 0;
    }

    private function transformDataForTable($rawData)
    {
        // Define branch mapping for abbreviations
        $branchMapping = [
            'PWM Pontianak' => 'PTK',
            'PWM Medan' => 'MDN', 
            'PWM Makassar' => 'MKS',
            'PWM Palembang' => 'PLB',
            'PWM Denpasar' => 'DPS',
            'PWM Surabaya' => 'SBY',
            'PWM Pekanbaru' => 'PKU',
            'PWM Cirebon' => 'CRB',
            'PWM Tangerang' => 'TGR',
            'PWM Bekasi' => 'BKS',
            'PWM Semarang' => 'SMG',
            'PWM Banjarmasin' => 'BJM',
            'PWM Bandung' => 'BDG',
            'PWM Lampung' => 'LMP',
            'PWM Jakarta' => 'JKT',
            'PWM Purwokerto' => 'PWT',
            'PWM Padang' => 'PDG'
        ];

        // Group data by product
        $groupedData = [];
        $nationalTotals = [];

        foreach ($rawData as $row) {
            $productKey = $row->product_name . '|' . $row->product_status;
            $branchAbbr = $branchMapping[$row->branch_name] ?? substr($row->branch_name, 0, 3);
            
            if (!isset($groupedData[$productKey])) {
                $groupedData[$productKey] = [
                    'product_name' => $row->product_name,
                    'product_status' => $row->product_status,
                    'branches' => []
                ];
            }

            $groupedData[$productKey]['branches'][$branchAbbr] = (float)$row->total_net;
            
            // Calculate national total
            if (!isset($nationalTotals[$productKey])) {
                $nationalTotals[$productKey] = 0;
            }
            $nationalTotals[$productKey] += (float)$row->total_net;
        }

        // Convert to table format
        $tableData = [];
        $no = 1;

        foreach ($groupedData as $productKey => $productData) {
            $rowData = [
                'no' => $no++,
                'nama_barang' => $productData['product_name'],
                'ket_pl' => $productData['product_status'],
                'mdn' => $productData['branches']['MDN'] ?? 0,
                'mks' => $productData['branches']['MKS'] ?? 0,
                'plb' => $productData['branches']['PLB'] ?? 0,
                'dps' => $productData['branches']['DPS'] ?? 0,
                'sby' => $productData['branches']['SBY'] ?? 0,
                'pku' => $productData['branches']['PKU'] ?? 0,
                'crb' => $productData['branches']['CRB'] ?? 0,
                'tgr' => $productData['branches']['TGR'] ?? 0,
                'bks' => $productData['branches']['BKS'] ?? 0,
                'smg' => $productData['branches']['SMG'] ?? 0,
                'bjm' => $productData['branches']['BJM'] ?? 0,
                'bdg' => $productData['branches']['BDG'] ?? 0,
                'lmp' => $productData['branches']['LMP'] ?? 0,
                'jkt' => $productData['branches']['JKT'] ?? 0,
                'ptk' => $productData['branches']['PTK'] ?? 0,
                'pwt' => $productData['branches']['PWT'] ?? 0,
                'pdg' => $productData['branches']['PDG'] ?? 0,
                'nasional' => $nationalTotals[$productKey]
            ];

            $tableData[] = $rowData;
        }

        return $tableData;
    }
}

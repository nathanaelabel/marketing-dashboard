<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\TableHelper;

class SalesFamilyController extends Controller
{
    public function index()
    {
        return view('sales-family');
    }

    public function getData(Request $request)
    {
        try {
            $month = $request->get('month', date('n'));
            $year = $request->get('year', date('Y'));
            $page = $request->get('page', 1);
            $type = $request->get('type', 'rp'); // 'rp' or 'pcs'
            $perPage = 50;
            $offset = ($page - 1) * $perPage;

            // Validate using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Get data and count using pagination strategy similar to optimized SalesItem
            $branchData = $this->getSalesFamilyData($month, $year, $offset, $perPage);
            $totalCount = $this->getTotalFamilyCount($month, $year);

            // Transform data using TableHelper - supports both amount and quantity
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_rp';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'family_name',
                $valueField,
                [] // No additional fields needed since we only use group1
            );

            // Build response using TableHelper
            $pagination = TableHelper::calculatePagination($page, $perPage, $totalCount);
            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json(TableHelper::successResponse($transformedData, $pagination, $period, [
                'type' => $type
            ]));
        } catch (\Exception $e) {
            TableHelper::logError('SalesFamilyController', 'getData', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'page' => $request->get('page'),
                'type' => $request->get('type')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getSalesFamilyData($month, $year, $offset, $perPage)
    {
        // Use optimized pagination strategy with CTE for consistent family count per page
        $qtyCalculation = TableHelper::getValueCalculation('qtyinvoiced');
        $amountCalculation = TableHelper::getValueCalculation('linenetamt');

        $query = "
            WITH family_sales AS (
                SELECT  
                    org.name as branch_name, 
                    prd.group1 as family_name,
                    {$qtyCalculation} AS total_qty,
                    {$amountCalculation} AS total_rp
                FROM c_invoiceline d
                INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
                INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
                INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
                WHERE h.ad_client_id = 1000001
                    AND h.isactive = 'Y'
                    AND h.docstatus IN ('CO', 'CL')
                    AND h.issotrx = 'Y'
                    AND d.qtyinvoiced > 0
                    AND d.linenetamt > 0
                    AND EXTRACT(month FROM h.dateinvoiced) = ?
                    AND EXTRACT(year FROM h.dateinvoiced) = ?
                GROUP BY org.name, prd.group1
            ),
            ranked_families AS (
                SELECT DISTINCT family_name,
                    ROW_NUMBER() OVER (ORDER BY family_name) as rn
                FROM family_sales
            ),
            paginated_families AS (
                SELECT family_name 
                FROM ranked_families 
                WHERE rn > ? AND rn <= ?
            )
            SELECT fs.branch_name, fs.family_name, fs.total_qty, fs.total_rp
            FROM family_sales fs
            INNER JOIN paginated_families pf ON fs.family_name = pf.family_name
            ORDER BY fs.family_name, fs.branch_name
        ";

        return DB::select($query, [$month, $year, $offset, $offset + $perPage]);
    }

    private function getTotalFamilyCount($month, $year)
    {
        $query = "
            SELECT COUNT(DISTINCT prd.group1) as total_count
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE h.ad_client_id = 1000001
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
}

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
            $type = $request->get('type', 'rp'); // 'rp' or 'pcs'

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Validate type parameter
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Get all data at once for client-side pagination
            $branchData = $this->getAllSalesFamilyData($month, $year, $type);

            // Transform data using TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_rp';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'family_name',
                $valueField,
                [] // No additional fields needed since we only use group1
            );

            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json([
                'data' => $transformedData,
                'period' => $period,
                'type' => $type,
                'total_count' => count($transformedData)
            ]);
        } catch (\Exception $e) {
            TableHelper::logError('SalesFamilyController', 'getData', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'type' => $request->get('type')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getAllSalesFamilyData($month, $year, $type)
    {
        // Choose field based on type
        $valueField = $type === 'pcs' ? 'qtyinvoiced' : 'linenetamt';
        $totalField = $type === 'pcs' ? 'total_qty' : 'total_rp';

        // Get all sales family data at once for client-side pagination
        $salesQuery = "
            SELECT 
                org.name as branch_name, 
                prd.group1 as family_name,
                " . TableHelper::getValueCalculation($valueField) . " AS {$totalField}
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
            ORDER BY prd.group1
        ";

        return DB::select($salesQuery, [$month, $year]);
    }
}

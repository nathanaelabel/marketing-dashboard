<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\TableHelper;

class SalesItemController extends Controller
{
    public function index()
    {
        return view('sales-item');
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

            // Validate parameters using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Validate type parameter
            if (!in_array($type, ['rp', 'pcs'])) {
                return response()->json(['error' => 'Invalid type parameter'], 400);
            }

            // Get data and count
            $branchData = $this->getSalesItemData($month, $year, $type, $offset, $perPage);
            $totalCount = $this->getTotalItemCount($month, $year, $type);

            // Transform data using TableHelper
            $valueField = $type === 'pcs' ? 'total_qty' : 'total_net';
            $transformedData = TableHelper::transformDataForBranchTable(
                $branchData,
                'product_name',
                $valueField,
                ['product_status']
            );

            // Build response using TableHelper
            $pagination = TableHelper::calculatePagination($page, $perPage, $totalCount);
            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json(TableHelper::successResponse($transformedData, $pagination, $period, [
                'type' => $type
            ]));
        } catch (\Exception $e) {
            TableHelper::logError('SalesItemController', 'getData', $e, [
                'month' => $request->get('month'),
                'year' => $request->get('year'),
                'page' => $request->get('page'),
                'type' => $request->get('type')
            ]);

            return TableHelper::errorResponse();
        }
    }

    private function getSalesItemData($month, $year, $type, $offset, $perPage)
    {
        // Choose field based on type
        $valueField = $type === 'pcs' ? 'qtyinvoiced' : 'linenetamt';
        $totalField = $type === 'pcs' ? 'total_qty' : 'total_net';

        // First, get unique products with pagination applied directly to products
        $productQuery = "
            SELECT DISTINCT prd.name as product_name, prd.status as product_status
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            WHERE h.ad_client_id = 1000001
                AND h.isactive = 'Y'
                AND h.docstatus IN ('CO', 'CL')
                AND h.issotrx = 'Y'
                AND d.qtyinvoiced > 0
                AND d.linenetamt > 0
                AND EXTRACT(month FROM h.dateinvoiced) = ?
                AND EXTRACT(year FROM h.dateinvoiced) = ?
            ORDER BY prd.name
            LIMIT ? OFFSET ?
        ";

        $products = DB::select($productQuery, [$month, $year, $perPage, $offset]);

        if (empty($products)) {
            return [];
        }

        // Create array of product names for IN clause
        $productNames = array_map(fn($p) => $p->product_name, $products);
        $productStatuses = array_map(fn($p) => $p->product_status, $products);

        // Build the main query with product names filter
        $placeholders = str_repeat('?,', count($productNames) - 1) . '?';

        $selectFields = "
            org.name as branch_name, 
            prd.name as product_name, 
            prd.status as product_status,
            " . TableHelper::getValueCalculation($valueField) . " AS {$totalField}";

        $additionalConditions = "
            AND d.qtyinvoiced > 0 
            AND d.linenetamt > 0
            AND prd.name IN ({$placeholders})";
        $groupBy = "org.name, prd.name, prd.status";
        $orderBy = "prd.name";

        $query = TableHelper::buildBaseSalesQuery($selectFields, '', $additionalConditions, $groupBy, $orderBy);

        $params = array_merge([$month, $year], $productNames);

        return DB::select($query, $params);
    }

    private function getTotalItemCount($month, $year, $type)
    {
        // Count unique products only (not product-status combinations)
        $countField = "prd.name";
        $additionalConditions = "AND d.qtyinvoiced > 0 AND d.linenetamt > 0";

        $query = TableHelper::buildCountQuery($countField, '', $additionalConditions);

        $result = DB::select($query, [$month, $year]);
        return $result[0]->total_count ?? 0;
    }
}

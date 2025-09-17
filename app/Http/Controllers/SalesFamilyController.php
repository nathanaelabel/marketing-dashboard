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
            $type = $request->get('type', 'amount'); // 'amount' or 'quantity'
            $perPage = 50;
            $offset = ($page - 1) * $perPage;

            // Validate using TableHelper
            $validationErrors = TableHelper::validatePeriodParameters($month, $year);
            if (!empty($validationErrors)) {
                return response()->json(['error' => $validationErrors[0]], 400);
            }

            // Get data and count
            $branchData = $this->getSalesFamilyData($month, $year, $type, $offset, $perPage);
            $totalCount = $this->getTotalFamilyCount($month, $year);

            // Transform data using TableHelper - supports both amount and quantity
            $valueField = $type === 'quantity' ? 'total_qty' : 'total_rp';
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

    private function getSalesFamilyData($month, $year, $type, $offset, $perPage)
    {
        // Build both calculations since we return both values
        $qtyCalculation = TableHelper::getValueCalculation('qtyinvoiced');
        $amountCalculation = TableHelper::getValueCalculation('linenetamt');
        
        $selectFields = "
            org.name as branch_name, 
            prd.group1 as family_name,
            {$qtyCalculation} AS total_qty,
            {$amountCalculation} AS total_rp";
        
        $additionalConditions = "AND d.qtyinvoiced > 0 AND d.linenetamt > 0";
        $groupBy = "org.name, prd.group1";
        $orderBy = "org.name, prd.group1 LIMIT ? OFFSET ?";
        
        $query = TableHelper::buildBaseSalesQuery($selectFields, '', $additionalConditions, $groupBy, $orderBy);

        return DB::select($query, [$month, $year, $perPage, $offset]);
    }

    private function getTotalFamilyCount($month, $year)
    {
        $countField = "prd.group1";
        $additionalConditions = "AND d.qtyinvoiced > 0 AND d.linenetamt > 0";
        
        $query = TableHelper::buildCountQuery($countField, '', $additionalConditions);

        $result = DB::select($query, [$month, $year]);
        return $result[0]->total_count ?? 0;
    }
}

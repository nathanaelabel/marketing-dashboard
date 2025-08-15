<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesMetricsController extends Controller
{
    public function getData(Request $request)
    {
        try {
            $location = $request->input('location', 'National');
            $startDate = $request->input('start_date', Carbon::now()->subDays(21)->toDateString());
            $endDate = $request->input('end_date', Carbon::now()->toDateString());

            $locationFilter = ($location === 'National') ? '%' : $location;

            // 1. Sales Order Query
            $salesOrderQuery = "
            SELECT
              SUM(d.linenetamt) AS total_so,
              SUM(d.qtydelivered * d.priceactual) as total_completed_so,
              SUM((d.qtyordered - d.qtydelivered) * d.priceactual) AS total_pending_so
            FROM
              c_orderline d
              INNER JOIN c_order h ON d.c_order_id = h.c_order_id
              INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE
              h.ad_client_id = 1000001
              AND h.issotrx = 'Y'
              AND h.docstatus = 'CO'
              AND d.linenetamt > 0
              AND h.isactive = 'Y'
              AND org.name like ?
              AND DATE(h.dateordered) BETWEEN ? AND ?
        ";
            $salesOrderData = DB::selectOne($salesOrderQuery, [$locationFilter, $startDate, $endDate]);

            // 2. Stock Value Query
            $stockValueQuery = "
            SELECT 
                SUM(s.qtyonhand * pp.pricestd) AS stock_value
            FROM 
                m_storage s
            JOIN m_product p ON s.m_product_id = p.m_product_id
            JOIN m_productprice pp ON p.m_product_id = pp.m_product_id
            JOIN m_pricelist_version plv ON pp.m_pricelist_version_id = plv.m_pricelist_version_id
            JOIN m_locator loc ON s.m_locator_id = loc.m_locator_id
            JOIN ad_org org ON s.ad_org_id = org.ad_org_id
            WHERE 
                loc.value = 'Main'
            AND org.name LIKE ?
        ";
            $stockValueData = DB::selectOne($stockValueQuery, [$locationFilter]);

            // 3. Store Returns Query
            $storeReturnsQuery = "
            SELECT
              SUM(d.linenetamt) AS store_returns
            FROM
              c_invoiceline d
              INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
              INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE
              org.name LIKE ?
              AND h.issotrx = 'Y'
              AND d.qtyinvoiced > 0
              AND d.linenetamt > 0
              AND h.docstatus in ('CO', 'CL')
              AND h.documentno LIKE 'CNC%'
              AND DATE(h.dateordered) BETWEEN ? AND ?
        ";
            $storeReturnsData = DB::selectOne($storeReturnsQuery, [$locationFilter, $startDate, $endDate]);

            // 4. Accounts Receivable Pie Chart Query
            $arPieQuery = "
            SELECT 
                SUM(CASE WHEN h.duedate >= CURRENT_DATE - INTERVAL '30 days' THEN h.grandtotal ELSE 0 END) as days_1_30,
                SUM(CASE WHEN h.duedate BETWEEN CURRENT_DATE - INTERVAL '60 days' AND CURRENT_DATE - INTERVAL '31 days' THEN h.grandtotal ELSE 0 END) as days_31_60,
                SUM(CASE WHEN h.duedate BETWEEN CURRENT_DATE - INTERVAL '90 days' AND CURRENT_DATE - INTERVAL '61 days' THEN h.grandtotal ELSE 0 END) as days_61_90,
                SUM(CASE WHEN h.duedate < CURRENT_DATE - INTERVAL '90 days' THEN h.grandtotal ELSE 0 END) as days_90_plus
            FROM c_invoice_v h
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE h.ad_client_id = 1000001 
            AND h.issotrx = 'Y' 
            AND h.docstatus IN ('CO', 'CL') 
            AND h.ispaid = 'N'
            AND org.name LIKE ?
        ";
            $arPieData = DB::selectOne($arPieQuery, [$locationFilter]);

            $formattedStartDate = Carbon::parse($startDate)->format('j M Y');
            $formattedEndDate = Carbon::parse($endDate)->format('j M Y');

            $arPieChartData = [
                'labels' => ['1 - 30 Days', '31 - 60 Days', '61 - 90 Days', '> 90 Days'],
                'data' => [
                    (float)($arPieData->days_1_30 ?? 0),
                    (float)($arPieData->days_31_60 ?? 0),
                    (float)($arPieData->days_61_90 ?? 0),
                    (float)($arPieData->days_90_plus ?? 0),
                ],
                'total' => array_sum([
                    (float)($arPieData->days_1_30 ?? 0),
                    (float)($arPieData->days_31_60 ?? 0),
                    (float)($arPieData->days_61_90 ?? 0),
                    (float)($arPieData->days_90_plus ?? 0),
                ])
            ];

            return response()->json([
                'total_so' => (float)($salesOrderData->total_so ?? 0),
                'completed_so' => (float)($salesOrderData->total_completed_so ?? 0),
                'pending_so' => (float)($salesOrderData->total_pending_so ?? 0),
                'stock_value' => (float)($stockValueData->stock_value ?? 0),
                'store_returns' => (float)($storeReturnsData->store_returns ?? 0),
                'ar_pie_chart' => $arPieChartData,
                'date_range' => $formattedStartDate . ' - ' . $formattedEndDate,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLocations()
    {
        try {
            $locations = DB::table('ad_org')
                ->where('isactive', 'Y')
                ->orderBy('name')
                ->pluck('name');

            return response()->json($locations);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesMetricsController extends Controller
{
    public function getData(Request $request)
    {
        try {
            $location = $request->input('location', 'National');
            $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(21)))->format('Y-m-d');
            $endDate = Carbon::parse($request->input('end_date', Carbon::now()))->format('Y-m-d');

            $locationFilter = ($location === 'National') ? '%' : $location;

            // 1. Sales Order Query
            $salesOrderQuery = "
            SELECT
              SUM(d.linenetamt) AS total_so,
              SUM(d.qtydelivered * d.priceactual) AS total_completed_so,
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
              AND DATE(h.dateordered) BETWEEN ? AND ?
            ";
            $soBindings = [$startDate, $endDate];
            if ($location !== 'National') {
                $salesOrderQuery .= " AND org.name LIKE ?";
                $soBindings[] = $locationFilter;
            }
            $salesOrderData = DB::selectOne($salesOrderQuery, $soBindings);

            // 2. Stock Value Query - Current stock value (point-in-time calculation)
            $stockValueQuery = "
            SELECT
              SUM(s.qtyonhand * prc.pricelist * 0.615) as stock_value
            FROM
              m_storage s
              INNER JOIN m_product prd on s.m_product_id = prd.m_product_id
              INNER JOIN m_productprice prc on prd.m_product_id = prc.m_product_id
              INNER JOIN m_pricelist_version plv ON prc.m_pricelist_version_id = plv.m_pricelist_version_id
              INNER JOIN m_locator loc ON s.m_locator_id = loc.m_locator_id
              INNER JOIN ad_org org ON s.ad_org_id = org.ad_org_id
            WHERE
              UPPER(plv.name) LIKE '%PURCHASE%'
              AND plv.isactive = 'Y'
              AND s.qtyonhand > 0
            ";

            $stockValueBindings = [];
            if ($location !== 'National') {
                $stockValueQuery .= " AND org.name LIKE ?";
                $stockValueBindings[] = $locationFilter;
            }

            $stockValueData = DB::selectOne($stockValueQuery, $stockValueBindings);

            // 3. Store Returns Query
            $storeReturnsQuery = "
            SELECT
              SUM(d.linenetamt) AS store_returns
            FROM
              c_invoiceline d
              INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
              INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            WHERE
              h.issotrx = 'Y'
              AND h.ad_client_id = 1000001
              AND d.qtyinvoiced > 0
              AND d.linenetamt > 0
              AND h.docstatus in ('CO', 'CL')
              AND h.documentno LIKE 'CNC%'
              AND DATE(h.dateinvoiced) BETWEEN ? AND ?
            ";
            $srBindings = [$startDate, $endDate];
            if ($location !== 'National') {
                $storeReturnsQuery .= " AND org.name LIKE ?";
                $srBindings[] = $locationFilter;
            }
            $storeReturnsData = DB::selectOne($storeReturnsQuery, $srBindings);

            // 4. Accounts Receivable Pie Chart Query
            $paymentsSubquery = "
                SELECT
                    c_invoice_id,
                    SUM(amount + discountamt + writeoffamt) as paidamt
                FROM c_allocationline
                GROUP BY c_invoice_id
            ";

            $overdueQuery = "
                SELECT
                    inv.ad_org_id,
                    inv.grandtotal - COALESCE(p.paidamt, 0) as open_amount,
                    DATE_PART('day', NOW() - inv.dateinvoiced::date) as age
                FROM c_invoice as inv
                LEFT JOIN ({$paymentsSubquery}) as p ON inv.c_invoice_id = p.c_invoice_id
                WHERE inv.issotrx = 'Y'
                AND inv.docstatus = 'CO'
                AND (inv.grandtotal - COALESCE(p.paidamt, 0)) > 0.01
            ";

            $arPieQuery = "
                SELECT
                    SUM(CASE WHEN overdue.age BETWEEN 1 AND 30 THEN overdue.open_amount ELSE 0 END) as days_1_30,
                    SUM(CASE WHEN overdue.age BETWEEN 31 AND 60 THEN overdue.open_amount ELSE 0 END) as days_31_60,
                    SUM(CASE WHEN overdue.age BETWEEN 61 AND 90 THEN overdue.open_amount ELSE 0 END) as days_61_90,
                    SUM(CASE WHEN overdue.age > 90 THEN overdue.open_amount ELSE 0 END) as days_90_plus
                FROM ad_org as org
                JOIN ({$overdueQuery}) as overdue ON org.ad_org_id = overdue.ad_org_id
                WHERE overdue.age > 0
            ";
            $arBindings = [];
            if ($location !== 'National') {
                $arPieQuery .= " AND org.name LIKE ?";
                $arBindings[] = $locationFilter;
            }

            $arPieData = DB::selectOne($arPieQuery, $arBindings);

            $dateRange = ChartHelper::formatDateRange($startDate, $endDate);

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
                'date_range' => $dateRange,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLocations()
    {
        try {
            $locations = ChartHelper::getLocations();

            // Add National option at the beginning
            $locationOptions = collect([
                [
                    'value' => '%',
                    'display' => 'National'
                ]
            ]);

            // Map locations to include both value (full name) and display name
            $branchOptions = $locations->map(function ($location) {
                return [
                    'value' => $location,
                    'display' => ChartHelper::getBranchDisplayName($location)
                ];
            });

            // Merge National option with branch options
            $allOptions = $locationOptions->merge($branchOptions);

            return response()->json($allOptions);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

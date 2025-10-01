<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsReceivableController extends Controller
{
    public function data(Request $request)
    {
        $currentDate = now()->toDateString();

        // Use CTE for better performance - pre-calculate payments in one pass
        $sql = "
        WITH payment_summary AS (
            SELECT 
                alocln.c_invoice_id,
                SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt) as bayar
            FROM c_allocationline alocln
            INNER JOIN c_allocationhdr alochdr ON alocln.c_allocationhdr_id = alochdr.c_allocationhdr_id
            WHERE alochdr.docstatus IN ('CO', 'IN')
                AND alochdr.ad_client_id = 1000001
                AND alochdr.datetrx <= ?
            GROUP BY alocln.c_invoice_id
        ),
        invoice_data AS (
            SELECT 
                inv.c_invoice_id,
                inv.totallines,
                inv.dateinvoiced,
                org.name as branch_name,
                COALESCE(ps.bayar, 0) as bayar,
                CASE 
                    WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NDC') THEN 1
                    ELSE -1 
                END as pengali,
                (CURRENT_DATE - inv.dateinvoiced::date) as age
            FROM c_invoice inv
            INNER JOIN c_bpartner bp ON inv.c_bpartner_id = bp.c_bpartner_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
            LEFT JOIN payment_summary ps ON ps.c_invoice_id = inv.c_invoice_id
            WHERE SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NCC', 'CNC', 'NDC')
                AND inv.isactive = 'Y'
                AND inv.ad_client_id = 1000001
                AND bp.isactive = 'Y'
                AND inv.issotrx = 'Y'
                AND inv.docstatus IN ('CO', 'CL')
                AND bp.iscustomer = 'Y'
                AND inv.dateinvoiced <= ?
                AND inv.c_bpartner_id IS NOT NULL
                AND inv.totallines IS NOT NULL
        )
        SELECT 
            branch_name,
            SUM(CASE WHEN age >= 1 AND age <= 30 AND (totallines - (bayar * pengali)) <> 0 
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_1_30,
            SUM(CASE WHEN age >= 31 AND age <= 60 AND (totallines - (bayar * pengali)) <> 0 
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_31_60,
            SUM(CASE WHEN age >= 61 AND age <= 90 AND (totallines - (bayar * pengali)) <> 0 
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_61_90,
            SUM(CASE WHEN age > 90 AND (totallines - (bayar * pengali)) <> 0 
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_90_plus,
            SUM(CASE WHEN age >= 1 AND (totallines - (bayar * pengali)) <> 0 
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM invoice_data
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $queryResult = collect(DB::select($sql, [$currentDate, $currentDate]))
            ->map(function ($item) {
                $item->overdue_1_30 = (float) $item->overdue_1_30;
                $item->overdue_31_60 = (float) $item->overdue_31_60;
                $item->overdue_61_90 = (float) $item->overdue_61_90;
                $item->overdue_90_plus = (float) $item->overdue_90_plus;
                $item->total_overdue = (float) $item->total_overdue;
                return $item;
            });

        $formattedData = ChartHelper::formatAccountsReceivableData($queryResult, $currentDate);

        return response()->json($formattedData);
    }
}

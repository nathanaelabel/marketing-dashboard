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

        // Subquery to calculate the total amount paid for each invoice
        $paymentsSubquery = DB::table('c_allocationline')
            ->select(
                'c_invoice_id',
                DB::raw('SUM(amount + discountamt + writeoffamt) as paidamt')
            )
            ->groupBy('c_invoice_id');

        // Subquery to get open invoices and their age
        $overdueQuery = DB::table('c_invoice as inv')
            ->leftJoinSub($paymentsSubquery, 'p', function ($join) {
                $join->on('inv.c_invoice_id', '=', 'p.c_invoice_id');
            })
            ->select(
                'inv.ad_org_id',
                DB::raw("inv.grandtotal - COALESCE(p.paidamt, 0) as open_amount"),
                DB::raw("DATE_PART('day', NOW() - inv.dateinvoiced::date) as age")
            )
            ->where('inv.issotrx', 'Y')
            ->where('inv.docstatus', 'CO')
            ->where(DB::raw("inv.grandtotal - COALESCE(p.paidamt, 0)"), '>', 0.01);

        // Final query to aggregate by branch and aging buckets
        $queryResult = DB::table('ad_org as org')
            ->joinSub($overdueQuery, 'overdue', function ($join) {
                $join->on('org.ad_org_id', '=', 'overdue.ad_org_id');
            })
            ->select(
                'org.name as branch_name',
                DB::raw("SUM(CASE WHEN overdue.age BETWEEN 1 AND 30 THEN overdue.open_amount ELSE 0 END) as overdue_1_30"),
                DB::raw("SUM(CASE WHEN overdue.age BETWEEN 31 AND 60 THEN overdue.open_amount ELSE 0 END) as overdue_31_60"),
                DB::raw("SUM(CASE WHEN overdue.age BETWEEN 61 AND 90 THEN overdue.open_amount ELSE 0 END) as overdue_61_90"),
                DB::raw("SUM(CASE WHEN overdue.age > 90 THEN overdue.open_amount ELSE 0 END) as overdue_90_plus"),
                DB::raw("SUM(overdue.open_amount) as total_overdue")
            )
            ->where('overdue.age', '>', 0)
            ->groupBy('org.name')
            ->orderBy('total_overdue', 'desc')
            ->get()
            ->map(function ($item) {
                $item->overdue_1_30 = (float) $item->overdue_1_30;
                $item->overdue_31_60 = (float) $item->overdue_31_60;
                $item->overdue_61_90 = (float) $item->overdue_61_90;
                $item->overdue_90_plus = (float) $item->overdue_90_plus;
                return $item;
            });

        // The formatting method will be created in the next step
        $formattedData = ChartHelper::formatAccountsReceivableData($queryResult, $currentDate);

        return response()->json($formattedData);
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NationalRevenueController extends Controller
{

    public function data(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $organization = $request->input('organization', '%');

        $queryResult = DB::table('c_invoice as inv')
            ->join('c_invoiceline as invl', 'inv.c_invoice_id', '=', 'invl.c_invoice_id')
            ->join('ad_org as org', 'inv.ad_org_id', '=', 'org.ad_org_id')
            ->select('org.name as branch_name', DB::raw('SUM(invl.linenetamt) as total_revenue'))
            ->where('inv.ad_client_id', 1000001)
            ->where('inv.issotrx', 'Y')
            ->where('invl.qtyinvoiced', '>', 0)
            ->where('invl.linenetamt', '>', 0)
            ->whereIn('inv.docstatus', ['CO', 'CL'])
            ->where('inv.isactive', 'Y')
            ->where('org.name', 'like', $organization)
            ->whereBetween(DB::raw('DATE(inv.dateinvoiced)'), [$startDate, $endDate])
            ->groupBy('org.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $formattedData = ChartHelper::formatNationalRevenueData($queryResult);

        return response()->json($formattedData);
    }
}

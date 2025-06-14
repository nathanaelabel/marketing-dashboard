<?php

namespace App\Http\Controllers;

use App\Models\NationalRevenue;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NationalRevenueController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $revenues = NationalRevenue::whereBetween('invoice_date', [$startDate, $endDate])->get();

        $totalRevenue = $revenues->sum('total_revenue');

        $branchMap = [
            'PWM Surabaya' => 'SBY',
            'PWM Jakarta' => 'JKT',
            'PWM Bandung' => 'BDG',
        ];

        $revenueByBranch = $revenues->groupBy(function ($item, $key) use ($branchMap) {
            return $branchMap[$item['branch_name']] ?? $item['branch_name'];
        })->map(function ($group) {
            return $group->sum('total_revenue');
        });

        // This ensures SBY, JKT, and BDG always appear on the chart, even with 0 revenue.
        $allBranches = collect([
            'SBY' => 0,
            'JKT' => 0,
            'BDG' => 0,
        ]);

        $branchRevenue = $allBranches->merge($revenueByBranch);

        return view('dashboard', [
            'totalRevenue' => $totalRevenue,
            'branchRevenue' => $branchRevenue,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}

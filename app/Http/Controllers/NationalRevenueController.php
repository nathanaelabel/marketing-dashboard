<?php

namespace App\Http\Controllers;

use App\Models\NationalRevenue;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\ChartHelper;

class NationalRevenueController extends Controller
{
    public function index(Request $request)
    {
        // Pass default dates to the view for the initial AJAX call
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        return view('dashboard', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'currentDateFormatted' => \Carbon\Carbon::now()->format('l, d F Y'),
        ]);
    }

    public function getChartData(Request $request)
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

        // Ensure SBY, JKT, BDG are always present in the output for consistent chart labels
        $allBranches = collect([
            'SBY' => 0,
            'JKT' => 0,
            'BDG' => 0,
        ]);

        $branchRevenueData = $allBranches->merge($revenueByBranch);

        // Calculate the maximum branch revenue for Y-axis scaling
        $maxBranchRevenue = 0;
        if ($branchRevenueData->isNotEmpty()) {
            $maxBranchRevenue = $branchRevenueData->values()->max();
        }

        $allBranchValues = $branchRevenueData->values()->all();
        $yAxisConfig = ChartHelper::getYAxisConfig($maxBranchRevenue, null, $allBranchValues);
        // Pass the yAxisConfig divisor to calculateSuggestedMax, as it might have changed based on average
        $suggestedMaxY = ChartHelper::calculateSuggestedMax($maxBranchRevenue, $yAxisConfig['divisor']);

        return response()->json([
            'totalRevenue' => $totalRevenue,
            'labels' => $branchRevenueData->keys(),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $branchRevenueData->values(),
                ]
            ],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMaxY,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\OverdueAccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\ChartHelper;

class AccountsReceivableController extends Controller
{
    public function index()
    {
        return view('accounts-receivable', [
            'currentDateFormatted' => Carbon::now()->format('l, d F Y'),
        ]);
    }

    public function getARChartData()
    {
        $latestCalculationDate = OverdueAccountsReceivable::max('calculation_date');

        if (!$latestCalculationDate) {
            return response()->json([
                'labels' => [],
                'data_1_30' => [],
                'data_31_60' => [],
                'data_61_90' => [],
                'data_over_90' => [],
                'totalOverdue' => 0,
                'lastUpdate' => 'Data not available',
            ]);
        }

        $branchOrder = [
            'PWM Surabaya',
            'PWM Jakarta',
            'PWM Bandung',
            'TGR',
            'BKS',
            'PTK',
            'LMP',
            'BJM',
            'CRB',
            'MKS',
            'SMG',
            'PWT',
            'DPS',
            'PLB',
            'PDG',
            'MDN',
            'PKU'
        ];

        $orderClause = "CASE branch_name ";
        foreach ($branchOrder as $index => $branchName) {
            $orderClause .= "WHEN '{$branchName}' THEN " . ($index + 1) . " ";
        }
        $orderClause .= "ELSE " . (count($branchOrder) + 1) . " END";

        $arDataByBranch = OverdueAccountsReceivable::where('calculation_date', $latestCalculationDate)
            ->orderByRaw($orderClause)
            ->get();

        $totalOverdue = $arDataByBranch->sum(function ($item) {
            return $item->days_1_30_overdue_amount +
                $item->days_31_60_overdue_amount +
                $item->days_61_90_overdue_amount +
                $item->days_over_90_overdue_amount;
        });

        $lastUpdate = Carbon::parse($latestCalculationDate)->format('l, d F Y');

        // Calculate the maximum individual stack total for Y-axis scaling
        $maxIndividualStackTotal = 0;
        if ($arDataByBranch->isNotEmpty()) {
            $maxIndividualStackTotal = $arDataByBranch->reduce(function ($max, $item) {
                $currentStack = $item->days_1_30_overdue_amount +
                    $item->days_31_60_overdue_amount +
                    $item->days_61_90_overdue_amount +
                    $item->days_over_90_overdue_amount;
                return $currentStack > $max ? $currentStack : $max;
            }, 0);
        }

        $yAxisConfig = ChartHelper::getYAxisConfig($maxIndividualStackTotal);
        // The client-side JS also calculates a suggestedMax. We provide one from the server
        // based on our helper to ensure consistency and allow more complex server-side logic if needed.
        // The client-side logic for suggestedMax will be updated to use this or be simplified.
        $suggestedMaxY = ChartHelper::calculateSuggestedMax($maxIndividualStackTotal, $yAxisConfig['divisor']);

        $labels = $arDataByBranch->pluck('branch_name')->map(function ($branch) {
            $shortcodes = [
                'PWM Surabaya' => 'SBY',
                'PWM Jakarta' => 'JKT',
                'PWM Bandung' => 'BDG',
            ];
            return $shortcodes[$branch] ?? strtoupper(substr($branch, 0, 3));
        });

        return response()->json([
            'labels' => $labels,
            'data_1_30' => $arDataByBranch->pluck('days_1_30_overdue_amount'),
            'data_31_60' => $arDataByBranch->pluck('days_31_60_overdue_amount'),
            'data_61_90' => $arDataByBranch->pluck('days_61_90_overdue_amount'),
            'data_over_90' => $arDataByBranch->pluck('days_over_90_overdue_amount'),
            'totalOverdue' => $totalOverdue,
            'lastUpdate' => $lastUpdate,
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMaxY, // Renamed from suggestedMaxVal for clarity
        ]);
    }
}

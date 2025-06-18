<?php

namespace App\Http\Controllers;

use App\Models\OverdueAccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        $arDataByBranch = OverdueAccountsReceivable::where('calculation_date', $latestCalculationDate)
            ->orderBy('branch_name')
            ->get();

        $totalOverdue = $arDataByBranch->sum(function ($item) {
            return $item->days_1_30_overdue_amount +
                $item->days_31_60_overdue_amount +
                $item->days_61_90_overdue_amount +
                $item->days_over_90_overdue_amount;
        });

        $lastUpdate = Carbon::parse($latestCalculationDate)->format('l, d F Y');

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
        ]);
    }
}

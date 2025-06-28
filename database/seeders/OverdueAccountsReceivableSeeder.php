<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OverdueAccountsReceivable;
use Carbon\Carbon;

class OverdueAccountsReceivableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            'TGR' => 'Tangerang',
            'BKS' => 'Bekasi',
            'PTK' => 'Pontianak',
            'LMP' => 'Lampung',
            'BJM' => 'Banjarmasin',
            'CRB' => 'Cirebon',
            'MKS' => 'Makassar',
            'SMG' => 'Semarang',
            'PWT' => 'Purwokerto',
            'DPS' => 'Denpasar',
            'PLB' => 'Palembang',
            'PDG' => 'Padang',
            'MDN' => 'Medan',
            'PKU' => 'Pekanbaru',
        ];

        $calculationDate = Carbon::create(2025, 6, 25)->format('Y-m-d');

        foreach ($branches as $branchCode => $branchName) {
            $totalOverdue = rand(4_000_000_000, 7_000_000_000);

            $pivots = [
                rand(10, 30), // 10-30%
                rand(31, 60), // 31-60%
                rand(61, 90), // 61-90%
            ];
            sort($pivots);
            $percentages = [
                $pivots[0],
                $pivots[1] - $pivots[0],
                $pivots[2] - $pivots[1],
                100 - $pivots[2],
            ];

            // Hitung jumlah per bucket
            $bucketAmounts = array_map(function ($pct) use ($totalOverdue) {
                return intval($totalOverdue * $pct / 100);
            }, $percentages);

            OverdueAccountsReceivable::create([
                'branch_name'                   => $branchCode,
                'calculation_date'              => $calculationDate,
                'days_1_30_overdue_amount'      => $bucketAmounts[0],
                'days_31_60_overdue_amount'     => $bucketAmounts[1],
                'days_61_90_overdue_amount'     => $bucketAmounts[2],
                'days_over_90_overdue_amount'   => $bucketAmounts[3],
            ]);
        }
    }
}

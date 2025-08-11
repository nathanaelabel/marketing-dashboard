<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NationalRevenue;
use Carbon\Carbon;

class NationalRevenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
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

        $startDate = Carbon::create(2025, 5, 1);
        $endDate = Carbon::create(2025, 5, 30);

        // Kelompokkan cabang berdasarkan target total bulanan agar variasi lebih lebar
        $tier1 = ['BKS', 'PTK', 'CRB']; // ~1 - 2 Miliar
        $tier2 = ['TGR', 'LMP', 'MKS', 'PWT', 'DPS', 'PLB']; // ~2 - 3 Miliar
        $tier3 = ['SMG', 'PDG', 'PKU']; // ~3 - 4 Miliar
        $tier4 = ['BJM']; // ~4 Miliar (lebih tinggi)

        foreach ($branches as $branchCode => $branchName) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Tentukan rentang revenue harian berdasarkan tier cabang
                if (in_array($branchCode, $tier1)) {
                    $min = 30000000;  // 30 juta
                    $max = 70000000;  // 70 juta  -> 0,9 - 2,1 M/bulan
                } elseif (in_array($branchCode, $tier2)) {
                    $min = 70000000;  // 70 juta
                    $max = 120000000; // 120 juta -> 2,1 - 3,6 M/bulan
                } elseif (in_array($branchCode, $tier3)) {
                    $min = 120000000; // 120 juta
                    $max = 170000000; // 170 juta -> 3,6 - 5,1 M/bulan
                } else { // tier4
                    $min = 170000000; // 170 juta
                    $max = 250000000; // 250 juta -> 5,1 - 7,5 M/bulan, target 4 M akan tercapai secara rata-rata
                }

                NationalRevenue::create([
                    'branch_name'   => $branchCode,
                    'invoice_date'  => $date->format('Y-m-d'),
                    'total_revenue' => rand($min, $max),
                ]);
            }
        }
    }
}

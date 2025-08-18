<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategoryItemController extends Controller
{
    public function getData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $categories = ['MIKA', 'SPARE PART', 'AKSESORIS', 'CAT', 'PRODUCT IMPORT'];

        $query = "
            SELECT
              org.name AS branch_name,
              cat.value AS category,
              SUM(d.linenetamt) AS total_revenue
            FROM c_invoiceline d
            INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
            INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
            INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
            INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
            WHERE h.ad_client_id = 1000001
              AND h.issotrx = 'Y'
              AND d.qtyinvoiced > 0
              AND d.linenetamt > 0
              AND h.docstatus IN ('CO', 'CL')
              AND h.isactive = 'Y'
              AND DATE(h.dateinvoiced) BETWEEN ? AND ?
              AND cat.value IN (" . implode(',', array_fill(0, count($categories), '?')) . ")
            GROUP BY org.name, cat.value
            ORDER BY org.name, cat.value;
        ";

        $bindings = array_merge([$startDate, $endDate], $categories);

        $data = DB::select($query, $bindings);

        // Process data for Marimekko chart
        $processedData = [];
        $branchTotals = [];

        foreach ($data as $row) {
            if (!isset($branchTotals[$row->branch_name])) {
                $branchTotals[$row->branch_name] = 0;
            }
            $branchTotals[$row->branch_name] += $row->total_revenue;
        }

        $datasets = [];
        $categoryColors = [
            'MIKA' => 'rgba(107, 187, 139, 0.8)', // Green
            'SPARE PART' => 'rgba(234, 127, 127, 0.8)', // Red
            'AKSESORIS' => 'rgba(130, 130, 234, 0.8)', // Blue
            'CAT' => 'rgba(238, 188, 109, 0.8)', // Orange
            'PRODUCT IMPORT' => 'rgba(203, 132, 203, 0.8)', // Purple
        ];

        foreach ($categories as $category) {
            $dataset = [
                'label' => $category,
                'data' => [],
                'backgroundColor' => $categoryColors[$category] ?? 'rgba(201, 203, 207, 0.8)'
            ];
            foreach (array_keys($branchTotals) as $branch) {
                $found = false;
                foreach ($data as $row) {
                    if ($row->branch_name === $branch && $row->category === $category) {
                        $dataset['data'][] = [
                            'x' => $branch,
                            'y' => (float)$row->total_revenue,
                            'v' => $branchTotals[$branch] // Total value for the bar (x-axis value)
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $dataset['data'][] = [
                        'x' => $branch,
                        'y' => 0,
                        'v' => $branchTotals[$branch]
                    ];
                }
            }
            $datasets[] = $dataset;
        }

        return response()->json([
            'labels' => array_keys($branchTotals),
            'datasets' => $datasets,
            'branchTotals' => $branchTotals
        ]);
    }
}

<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Sample Invoice Data ===\n\n";
$samples = DB::table('c_invoice')
    ->select('c_invoice_id', 'documentno', 'grandtotal', 'totallines', 'c_bpartner_id')
    ->limit(10)
    ->get();

foreach ($samples as $inv) {
    $bpartner = $inv->c_bpartner_id ?? 'NULL';
    echo "Invoice: {$inv->documentno} | GrandTotal: {$inv->grandtotal} | TotalLines: {$inv->totallines} | BPartner: {$bpartner}\n";
}

echo "\n=== Checking Invoice Distribution ===\n\n";

// Check total invoices with required fields
$total = DB::table('c_invoice')
    ->whereNotNull('c_bpartner_id')
    ->whereNotNull('totallines')
    ->count();
echo "Total invoices with c_bpartner_id and totallines: $total\n\n";

// Check by branch
echo "Distribution by branch (ad_org_id):\n";
$byOrg = DB::table('c_invoice')
    ->select('ad_org_id', DB::raw('COUNT(*) as cnt'))
    ->whereNotNull('c_bpartner_id')
    ->whereNotNull('totallines')
    ->groupBy('ad_org_id')
    ->orderByDesc('cnt')
    ->get();

foreach ($byOrg as $row) {
    $orgName = DB::table('ad_org')->where('ad_org_id', $row->ad_org_id)->value('name');
    echo "  $orgName (ID: {$row->ad_org_id}): {$row->cnt} invoices\n";
}

echo "\n=== Checking Overdue Invoices ===\n\n";

// Run the actual AR query
$currentDate = now()->toDateString();
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
    COUNT(*) as invoice_count,
    SUM(CASE WHEN (totallines - (bayar * pengali)) <> 0 THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN age >= 1 AND (totallines - (bayar * pengali)) <> 0 
        THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
FROM invoice_data
GROUP BY branch_name
ORDER BY total_overdue DESC
";

$result = DB::select($sql, [$currentDate, $currentDate]);

echo "Branches with overdue invoices:\n";
foreach ($result as $row) {
    echo "  {$row->branch_name}: {$row->overdue_count} overdue invoices, Total: Rp " . number_format($row->total_overdue, 0, ',', '.') . "\n";
}

echo "\nTotal branches with AR: " . count($result) . "\n";

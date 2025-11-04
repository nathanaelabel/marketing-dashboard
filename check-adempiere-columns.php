<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Adempiere Source Columns ===\n\n";

// Check one Adempiere connection
$connection = 'pgsql_jkt';

echo "Checking connection: $connection\n\n";

// Get column info from Adempiere
$columns = DB::connection($connection)
    ->select("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'c_invoice' 
        AND column_name IN ('totallines', 'c_bpartner_id', 'grandtotal')
        ORDER BY column_name
    ");

echo "Columns in Adempiere c_invoice table:\n";
foreach ($columns as $col) {
    echo "  - {$col->column_name} ({$col->data_type})\n";
}

// Get sample data
echo "\nSample invoice data from Adempiere:\n";
$sample = DB::connection($connection)
    ->table('c_invoice')
    ->select('c_invoice_id', 'documentno', 'grandtotal', 'totallines', 'c_bpartner_id')
    ->where('isactive', 'Y')
    ->limit(5)
    ->get();

foreach ($sample as $inv) {
    echo "  Invoice: {$inv->documentno} | GrandTotal: {$inv->grandtotal} | TotalLines: " .
        ($inv->totallines ?? 'NULL') . " | BPartner: " . ($inv->c_bpartner_id ?? 'NULL') . "\n";
}

echo "\n=== Checking Local Database ===\n\n";

$localSample = DB::table('c_invoice')
    ->select('c_invoice_id', 'documentno', 'grandtotal', 'totallines', 'c_bpartner_id')
    ->where('documentno', $sample[0]->documentno ?? 'INC-257215')
    ->first();

if ($localSample) {
    echo "Same invoice in local DB:\n";
    echo "  Invoice: {$localSample->documentno} | GrandTotal: {$localSample->grandtotal} | TotalLines: " .
        ($localSample->totallines ?? 'NULL') . " | BPartner: " . ($localSample->c_bpartner_id ?? 'NULL') . "\n";
} else {
    echo "Invoice not found in local DB\n";
}

<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Updating c_invoice columns from Adempiere ===\n\n";
$connections = config('database.sync_connections.adempiere', []);
$totalUpdated = 0;

foreach ($connections as $connection) {
    echo "Processing connection: $connection\n";
    
    try {
        // Count total first
        $total = DB::connection($connection)
            ->table('c_invoice')
            ->where('isactive', 'Y')
            ->count();
        
        echo "  Found $total invoices in Adempiere\n";
        
        // Use cursor to stream data without loading all into memory
        $updated = 0;
        $batchSize = 500;
        $batch = [];
        
        DB::connection($connection)
            ->table('c_invoice')
            ->select('c_invoice_id', 'totallines', 'c_bpartner_id')
            ->where('isactive', 'Y')
            ->orderBy('c_invoice_id')
            ->chunk($batchSize, function ($invoices) use (&$updated, &$batch, $batchSize) {
                // Prepare batch update using CASE WHEN for better performance
                $ids = [];
                $totallinesCases = [];
                $bpartnerCases = [];
                
                foreach ($invoices as $invoice) {
                    $ids[] = $invoice->c_invoice_id;
                    $totallinesCases[] = "WHEN {$invoice->c_invoice_id} THEN {$invoice->totallines}";
                    $bpartnerCases[] = "WHEN {$invoice->c_invoice_id} THEN {$invoice->c_bpartner_id}";
                }
                
                if (!empty($ids)) {
                    $idList = implode(',', $ids);
                    $totallinesSql = "CASE c_invoice_id " . implode(' ', $totallinesCases) . " END";
                    $bpartnerSql = "CASE c_invoice_id " . implode(' ', $bpartnerCases) . " END";
                    
                    DB::update("
                        UPDATE c_invoice 
                        SET totallines = $totallinesSql,
                            c_bpartner_id = $bpartnerSql
                        WHERE c_invoice_id IN ($idList)
                    ");
                    
                    $updated += count($ids);
                    echo "  Updated: $updated invoices\r";
                }
                
                // Force garbage collection
                unset($invoices, $ids, $totallinesCases, $bpartnerCases);
                gc_collect_cycles();
            });
        
        echo "\n  Completed: $updated invoices updated\n\n";
        $totalUpdated += $updated;
        
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Total Updated: $totalUpdated invoices ===\n";

// Verify
echo "\n=== Verification ===\n";
$withData = DB::table('c_invoice')
    ->whereNotNull('c_bpartner_id')
    ->where('totallines', '>', 0)
    ->count();

echo "Invoices with totallines > 0 and c_bpartner_id: $withData\n";

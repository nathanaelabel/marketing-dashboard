<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AccountsReceivableController extends Controller
{
    public function data(Request $request)
    {
        // Increase execution time limit for querying multiple databases
        set_time_limit(180); // 3 minutes
        ini_set('max_execution_time', 180);

        $currentDate = now()->toDateString();

        // Define all branch database connections
        $branchConnections = [
            'pgsql_jkt',
            'pgsql_bdg',
            'pgsql_smg',
            'pgsql_mdn',
            'pgsql_plb',
            'pgsql_bjm',
            'pgsql_dps',
            // 'pgsql_mks',
            'pgsql_pku',
            // 'pgsql_sby',
            'pgsql_ptk',
            'pgsql_crb',
            'pgsql_pdg',
            'pgsql_pwt',
            'pgsql_bks',
            'pgsql_lmp',
            'pgsql_trg',
        ];

        // Use correlated subquery to calculate payment per invoice (matching original query logic)
        $sql = "
        SELECT
            branch_name,
            SUM(CASE WHEN age >= 1 AND age <= 30 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_1_30,
            SUM(CASE WHEN age >= 31 AND age <= 60 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_31_60,
            SUM(CASE WHEN age >= 61 AND age <= 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_61_90,
            SUM(CASE WHEN age > 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_90_plus,
            SUM(CASE WHEN age >= 1 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM (
            SELECT
                inv.totallines,
                org.name as branch_name,
                -- Correlated subquery to get payment per invoice
                (
                    SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt), 0)
                    FROM c_allocationline alocln
                    INNER JOIN c_allocationhdr alochdr ON alocln.c_allocationhdr_id = alochdr.c_allocationhdr_id
                    WHERE alocln.c_invoice_id = inv.c_invoice_id
                        AND alochdr.docstatus IN ('CO', 'IN')
                        AND alochdr.ad_client_id = 1000001
                        AND alochdr.datetrx <= ?
                ) as bayar,
                CASE
                    WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NDC') THEN 1
                    ELSE -1
                END as pengali,
                (CURRENT_DATE - inv.dateinvoiced::date) as age
            FROM c_invoice inv
            INNER JOIN c_bpartner bp ON inv.c_bpartner_id = bp.c_bpartner_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
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
        ) as source
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        // Query all branch databases and collect results
        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            try {
                // Set shorter timeout per connection (30 seconds)
                DB::connection($connection)->statement("SET statement_timeout = 30000"); // 30 seconds

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                // Reset timeout
                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                // Log error and track failed branch
                Log::warning("Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                // Try to reset timeout even on error
                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                    // Ignore reset errors
                }

                // Continue to next branch without breaking
                continue;
            }
        }

        // Log summary of failed branches if any
        if (!empty($failedBranches)) {
            Log::info("Accounts Receivable - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) {
            $item->overdue_1_30 = (float) $item->overdue_1_30;
            $item->overdue_31_60 = (float) $item->overdue_31_60;
            $item->overdue_61_90 = (float) $item->overdue_61_90;
            $item->overdue_90_plus = (float) $item->overdue_90_plus;
            $item->total_overdue = (float) $item->total_overdue;
            return $item;
        });

        $formattedData = ChartHelper::formatAccountsReceivableData($queryResult, $currentDate);

        // Add failed branches information with abbreviations
        $failedBranchAbbreviations = [];
        foreach ($failedBranches as $connection) {
            // Convert connection name to abbreviation (e.g., pgsql_jkt -> JKT)
            $abbr = strtoupper(str_replace('pgsql_', '', $connection));
            $failedBranchAbbreviations[] = $abbr;
        }
        
        $formattedData['failedBranches'] = $failedBranchAbbreviations;

        return response()->json($formattedData);
    }

    public function exportExcel(Request $request)
    {
        // Increase execution time limit for export operations
        set_time_limit(300); // 5 minutes
        ini_set('max_execution_time', 300);

        $currentDate = now()->toDateString();

        // Define all branch database connections
        $branchConnections = [
            'pgsql_jkt',
            'pgsql_bdg',
            'pgsql_smg',
            'pgsql_mdn',
            'pgsql_plb',
            'pgsql_bjm',
            'pgsql_dps',
            'pgsql_mks',
            'pgsql_pku',
            'pgsql_sby',
            'pgsql_ptk',
            'pgsql_crb',
            'pgsql_pdg',
            'pgsql_pwt',
            'pgsql_bks',
            'pgsql_lmp',
            'pgsql_trg',
        ];

        // Use correlated subquery to calculate payment per invoice (matching original query logic)
        $sql = "
        SELECT
            branch_name,
            SUM(CASE WHEN age >= 1 AND age <= 30 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_1_30,
            SUM(CASE WHEN age >= 31 AND age <= 60 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_31_60,
            SUM(CASE WHEN age >= 61 AND age <= 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_61_90,
            SUM(CASE WHEN age > 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_90_plus,
            SUM(CASE WHEN age >= 1 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM (
            SELECT
                inv.totallines,
                org.name as branch_name,
                -- Correlated subquery to get payment per invoice
                (
                    SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt), 0)
                    FROM c_allocationline alocln
                    INNER JOIN c_allocationhdr alochdr ON alocln.c_allocationhdr_id = alochdr.c_allocationhdr_id
                    WHERE alocln.c_invoice_id = inv.c_invoice_id
                        AND alochdr.docstatus IN ('CO', 'IN')
                        AND alochdr.ad_client_id = 1000001
                        AND alochdr.datetrx <= ?
                ) as bayar,
                CASE
                    WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NDC') THEN 1
                    ELSE -1
                END as pengali,
                (CURRENT_DATE - inv.dateinvoiced::date) as age
            FROM c_invoice inv
            INNER JOIN c_bpartner bp ON inv.c_bpartner_id = bp.c_bpartner_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
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
        ) as source
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        // Query all branch databases and collect results
        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            try {
                // Set timeout per connection (60 seconds for export)
                DB::connection($connection)->statement("SET statement_timeout = 60000"); // 60 seconds

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                // Reset timeout
                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                // Log error and track failed branch
                Log::warning("Export Excel - Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                // Try to reset timeout even on error
                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                    // Ignore reset errors
                }

                // Continue to next branch without breaking
                continue;
            }
        }

        // Log summary of failed branches if any
        if (!empty($failedBranches)) {
            Log::info("Export Excel - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) {
            $item->overdue_1_30 = (float) $item->overdue_1_30;
            $item->overdue_31_60 = (float) $item->overdue_31_60;
            $item->overdue_61_90 = (float) $item->overdue_61_90;
            $item->overdue_90_plus = (float) $item->overdue_90_plus;
            $item->total_overdue = (float) $item->total_overdue;
            return $item;
        });

        // Calculate totals
        $total_1_30 = $queryResult->sum('overdue_1_30');
        $total_31_60 = $queryResult->sum('overdue_31_60');
        $total_61_90 = $queryResult->sum('overdue_61_90');
        $total_90_plus = $queryResult->sum('overdue_90_plus');
        $grandTotal = $queryResult->sum('total_overdue');

        // Format date for filename and display
        $formattedDate = \Carbon\Carbon::parse($currentDate)->format('d F Y');
        $fileDate = \Carbon\Carbon::parse($currentDate)->format('d-m-Y');
        $filename = 'Piutang_Usaha_' . $fileDate . '.xls';

        // Create XLS content using HTML table format
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $html = '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Piutang Usaha</x:Name>
                            <x:WorksheetOptions>
                                <x:Print>
                                    <x:ValidPrinterInfo/>
                                </x:Print>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
            <style>
                body { font-family: Verdana, sans-serif; }
                table { border-collapse: collapse; }
                th, td {
                    border: 1px solid #000;
                    padding: 6px 8px;
                    text-align: left;
                    font-family: Verdana, sans-serif;
                    font-size: 10pt;
                }
                th {
                    background-color: #D3D3D3;
                    color: #000;
                    font-weight: bold;
                    text-align: center;
                    vertical-align: middle;
                }
                .title {
                    font-family: Verdana, sans-serif;
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 8px;
                }
                .date {
                    font-family: Verdana, sans-serif;
                    font-size: 12pt;
                    margin-bottom: 15px;
                }
                .total-row { font-weight: bold; background-color: #E8E8E8; }
                .number { text-align: right; }
                .col-no { width: 70px; }
                .col-branch { width: 280px; }
                .col-code { width: 230px; }
                .col-amount { width: 350px; }
            </style>
        </head>
        <body>
            <div class="title">PIUTANG JATUH TEMPO</div>
            <div class="date">As of ' . $formattedDate . '</div>
            <br>
            <table>
                <colgroup>
                    <col class="col-no">
                    <col class="col-branch">
                    <col class="col-code">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-amount">
                </colgroup>
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA CABANG</th>
                        <th>KODE CABANG</th>
                        <th style="text-align: right;">1-30 DAYS (RP)</th>
                        <th style="text-align: right;">31-60 DAYS (RP)</th>
                        <th style="text-align: right;">61-90 DAYS (RP)</th>
                        <th style="text-align: right;">&gt; 90 DAYS (RP)</th>
                        <th style="text-align: right;">TOTAL (RP)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . number_format($row->overdue_1_30, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_31_60, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_61_90, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_90_plus, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->total_overdue, 2, '.', ',') . '</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($total_1_30, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_31_60, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_61_90, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_90_plus, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($grandTotal, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        // Increase execution time limit for export operations
        set_time_limit(300); // 5 minutes
        ini_set('max_execution_time', 300);

        $currentDate = now()->toDateString();

        // Define all branch database connections
        $branchConnections = [
            'pgsql_jkt',
            'pgsql_bdg',
            'pgsql_smg',
            'pgsql_mdn',
            'pgsql_plb',
            'pgsql_bjm',
            'pgsql_dps',
            'pgsql_mks',
            'pgsql_pku',
            'pgsql_sby',
            'pgsql_ptk',
            'pgsql_crb',
            'pgsql_pdg',
            'pgsql_pwt',
            'pgsql_bks',
            'pgsql_lmp',
            'pgsql_trg',
        ];

        // Use correlated subquery to calculate payment per invoice (matching original query logic)
        $sql = "
        SELECT
            branch_name,
            SUM(CASE WHEN age >= 1 AND age <= 30 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_1_30,
            SUM(CASE WHEN age >= 31 AND age <= 60 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_31_60,
            SUM(CASE WHEN age >= 61 AND age <= 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_61_90,
            SUM(CASE WHEN age > 90 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_90_plus,
            SUM(CASE WHEN age >= 1 AND (totallines - (bayar * pengali)) <> 0
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM (
            SELECT
                inv.totallines,
                org.name as branch_name,
                -- Correlated subquery to get payment per invoice
                (
                    SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt), 0)
                    FROM c_allocationline alocln
                    INNER JOIN c_allocationhdr alochdr ON alocln.c_allocationhdr_id = alochdr.c_allocationhdr_id
                    WHERE alocln.c_invoice_id = inv.c_invoice_id
                        AND alochdr.docstatus IN ('CO', 'IN')
                        AND alochdr.ad_client_id = 1000001
                        AND alochdr.datetrx <= ?
                ) as bayar,
                CASE
                    WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC', 'NDC') THEN 1
                    ELSE -1
                END as pengali,
                (CURRENT_DATE - inv.dateinvoiced::date) as age
            FROM c_invoice inv
            INNER JOIN c_bpartner bp ON inv.c_bpartner_id = bp.c_bpartner_id
            INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
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
        ) as source
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        // Query all branch databases and collect results
        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            try {
                // Set timeout per connection (60 seconds for export)
                DB::connection($connection)->statement("SET statement_timeout = 60000"); // 60 seconds

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                // Reset timeout
                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                // Log error and track failed branch
                Log::warning("Export PDF - Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                // Try to reset timeout even on error
                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                    // Ignore reset errors
                }

                // Continue to next branch without breaking
                continue;
            }
        }

        // Log summary of failed branches if any
        if (!empty($failedBranches)) {
            Log::info("Export PDF - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) {
            $item->overdue_1_30 = (float) $item->overdue_1_30;
            $item->overdue_31_60 = (float) $item->overdue_31_60;
            $item->overdue_61_90 = (float) $item->overdue_61_90;
            $item->overdue_90_plus = (float) $item->overdue_90_plus;
            $item->total_overdue = (float) $item->total_overdue;
            return $item;
        });

        // Calculate totals
        $total_1_30 = $queryResult->sum('overdue_1_30');
        $total_31_60 = $queryResult->sum('overdue_31_60');
        $total_61_90 = $queryResult->sum('overdue_61_90');
        $total_90_plus = $queryResult->sum('overdue_90_plus');
        $grandTotal = $queryResult->sum('total_overdue');

        // Format date for filename and display
        $formattedDate = \Carbon\Carbon::parse($currentDate)->format('d F Y');
        $fileDate = \Carbon\Carbon::parse($currentDate)->format('d-m-Y');

        // Create HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 20px; }
                body {
                    font-family: Verdana, sans-serif;
                    font-size: 9pt;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .title {
                    font-family: Verdana, sans-serif;
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .date {
                    font-family: Verdana, sans-serif;
                    font-size: 10pt;
                    color: #666;
                    margin-bottom: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 6px 8px;
                    text-align: left;
                    font-family: Verdana, sans-serif;
                    font-size: 9pt;
                }
                th {
                    background-color: #F5F5F5;
                    color: #000;
                    font-weight: bold;
                    text-align: center;
                    vertical-align: middle;
                }
                .number { text-align: right; }
                .total-row {
                    font-weight: bold;
                    background-color: #E8E8E8;
                }
                .total-row td {
                    border-top: 2px solid #333;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">PIUTANG JATUH TEMPO</div>
                <div class="date">As of ' . $formattedDate . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px; text-align: left;">NO</th>
                        <th style="width: 150px; text-align: left;">NAMA CABANG</th>
                        <th style="width: 70px; text-align: left;">KODE CABANG</th>
                        <th style="width: 100px; text-align: right;">1-30 DAYS</th>
                        <th style="width: 100px; text-align: right;">31-60 DAYS</th>
                        <th style="width: 100px; text-align: right;">61-90 DAYS</th>
                        <th style="width: 100px; text-align: right;">&gt; 90 DAYS</th>
                        <th style="width: 120px; text-align: right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . number_format($row->overdue_1_30, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_31_60, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_61_90, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->overdue_90_plus, 2, '.', ',') . '</td>
                <td class="number">' . number_format($row->total_overdue, 2, '.', ',') . '</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($total_1_30, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_31_60, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_61_90, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_90_plus, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($grandTotal, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Piutang_Jatuh_Tempo_' . $fileDate . '.pdf';

        return $pdf->download($filename);
    }
}

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
        // Tingkatkan batas waktu eksekusi untuk query multi-database
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $currentDate = $request->input('current_date', now()->toDateString());
        $filter = $request->input('filter', 'overdue');

        $branchOrder = ChartHelper::getBranchOrder();
        $branchConnections = [];
        foreach ($branchOrder as $branchName) {
            $connection = ChartHelper::getBranchConnection($branchName);
            if ($connection) {
                $branchConnections[] = $connection;
            }
        }

        // Cabang yang diketahui memiliki anomali data
        $anomalyBranches = ['pgsql_mks', 'pgsql_sby'];

        if ($filter === 'all') {
            // Filter All: tampilkan semua rentang umur piutang
            $sql = "
            SELECT
                branch_name,
                SUM(CASE WHEN age >= 0 AND age <= 104 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_0_104,
                SUM(CASE WHEN age >= 105 AND age <= 120 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_105_120,
                SUM(CASE WHEN age > 120 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_120_plus,
                SUM(CASE WHEN age >= 0 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
            FROM (
                SELECT
                    inv.totallines,
                    org.name as branch_name,
                    (
                        SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt
                            + CASE WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN alocln.overunderamt ELSE 0 END), 0)
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
                    (? ::date - inv.dateinvoiced::date) as age
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
            WHERE totallines <> abs(bayar * pengali)
                AND age >= 0
            GROUP BY branch_name
            ORDER BY total_overdue DESC
            ";
        } else {
            // Filter Overdue: hanya tampilkan piutang jatuh tempo
            $sql = "
            SELECT
                branch_name,
                SUM(CASE WHEN age >= 105 AND age <= 120 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_105_120,
                SUM(CASE WHEN age > 120 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as range_120_plus,
                SUM(CASE WHEN age >= 105 AND totallines > (bayar * pengali)
                    THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
            FROM (
                SELECT
                    inv.totallines,
                    org.name as branch_name,
                    (
                        SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt
                            + CASE WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN alocln.overunderamt ELSE 0 END), 0)
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
                    (? ::date - inv.dateinvoiced::date) as age
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
            WHERE totallines <> abs(bayar * pengali)
                AND age >= 105
            GROUP BY branch_name
            ORDER BY total_overdue DESC
            ";
        }

        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            if (in_array($connection, $anomalyBranches)) {
                continue;
            }

            if (!$this->canConnectToBranch($connection)) {
                Log::warning("Skipping {$connection} due to connectivity check failure");
                $failedBranches[] = $connection;
                continue;
            }

            try {
                // Timeout per koneksi 45 detik
                DB::connection($connection)->statement("SET statement_timeout = 45000");

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                Log::warning("Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                }
                continue;
            }
        }

        if (!empty($failedBranches)) {
            Log::info("Accounts Receivable - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) use ($filter) {
            if ($filter === 'all') {
                $item->range_0_104 = max(0, (float) ($item->range_0_104 ?? 0));
                $item->range_105_120 = max(0, (float) ($item->range_105_120 ?? 0));
                $item->range_120_plus = max(0, (float) ($item->range_120_plus ?? 0));
            } else {
                $item->range_105_120 = max(0, (float) ($item->range_105_120 ?? 0));
                $item->range_120_plus = max(0, (float) ($item->range_120_plus ?? 0));
            }
            $item->total_overdue = max(0, (float) $item->total_overdue);
            return $item;
        });

        $formattedData = ChartHelper::formatAccountsReceivableData(
            $queryResult,
            $currentDate,
            $filter,
            $branchConnections,
            $failedBranches,
            $anomalyBranches
        );

        return response()->json($formattedData);
    }

    // Cek konektivitas socket untuk menghindari timeout koneksi database yang lama
    protected function canConnectToBranch(string $connection, int $timeoutSeconds = 3): bool
    {
        $config = config("database.connections.{$connection}");

        if (!is_array($config)) {
            return false;
        }

        $host = $config['host'] ?? null;
        $port = (int)($config['port'] ?? 5432);

        if (empty($host) || $port <= 0) {
            return false;
        }

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errorNumber,
            $errorString,
            $timeoutSeconds
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }

    public function exportExcel(Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $currentDate = $request->input('current_date', now()->toDateString());
        $filter = 'all';

        $branchOrder = ChartHelper::getBranchOrder();
        $branchConnections = [];
        foreach ($branchOrder as $branchName) {
            $connection = ChartHelper::getBranchConnection($branchName);
            if ($connection) {
                $branchConnections[] = $connection;
            }
        }

        // Query dengan subquery korelasi untuk menghitung pembayaran per invoice
        $sql = "
        SELECT
            branch_name,
            SUM(CASE WHEN age >= 0 AND age <= 104 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_0_104,
            SUM(CASE WHEN age >= 105 AND age <= 120 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_105_120,
            SUM(CASE WHEN age > 120 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_120_plus,
            SUM(CASE WHEN age >= 0 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM (
            SELECT
                inv.totallines,
                org.name as branch_name,
                -- Correlated subquery to get payment per invoice
                (
                    SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt
                        + CASE WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN alocln.overunderamt ELSE 0 END), 0)
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
                (? ::date - inv.dateinvoiced::date) as age
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
        WHERE totallines <> abs(bayar * pengali)
            AND age >= 0
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            // Lakukan pengecekan konektivitas ringan terlebih dahulu untuk menghindari hang lama
            if (!$this->canConnectToBranch($connection)) {
                Log::warning("Export Excel - Skipping {$connection} due to connectivity check failure");
                $failedBranches[] = $connection;
                continue;
            }

            try {
                DB::connection($connection)->statement("SET statement_timeout = 30000");

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                Log::warning("Export Excel - Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                }

                continue;
            }
        }

        if (!empty($failedBranches)) {
            Log::info("Export Excel - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) {
            // Standarisasi nama cabang
            if ($item->branch_name === 'PT. Putra Mandiri Damai') {
                $item->branch_name = 'PWM Makassar';
            }
            if ($item->branch_name === 'PT. CIPTA ARDANA KENCANA') {
                $item->branch_name = 'MPM Tangerang';
            }

            $fields = ['overdue_0_104', 'overdue_105_120', 'overdue_120_plus', 'total_overdue'];
            foreach ($fields as $field) {
                $value = (float) ($item->{$field} ?? 0);
                $item->{$field} = (abs($value) < 1) ? 0.0 : $value;
            }

            return $item;
        });

        // Kecualikan cabang dengan anomali data (PWM Makassar, PWM Surabaya)
        $queryResult = $queryResult->reject(function ($item) {
            return in_array($item->branch_name, ['PWM Makassar', 'PWM Surabaya'], true);
        })->values();

        $queryResult = ChartHelper::sortByBranchOrder($queryResult, 'branch_name');

        $total_0_104 = $queryResult->sum('overdue_0_104');
        $total_105_120 = $queryResult->sum('overdue_105_120');
        $total_120_plus = $queryResult->sum('overdue_120_plus');
        $grandTotal = $queryResult->sum('total_overdue');

        $formattedDate = \Carbon\Carbon::parse($currentDate)->format('d F Y');
        $fileDate = \Carbon\Carbon::parse($currentDate)->format('d-m-Y');
        $filename = 'Piutang_Usaha_' . $fileDate . '.xls';

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
            <div class="title">UMUR PIUTANG USAHA</div>
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
                </colgroup>
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA CABANG</th>
                        <th>KODE CABANG</th>
                        <th style="text-align: right;">0-104 DAYS (RP)</th>
                        <th style="text-align: right;">105-120 DAYS (RP)</th>
                        <th style="text-align: right;">&gt;120 DAYS (RP)</th>
                        <th style="text-align: right;">TOTAL (RP)</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $val_0_104 = number_format($row->overdue_0_104, 2, '.', ',');
            $val_105_120 = number_format($row->overdue_105_120, 2, '.', ',');
            $val_120_plus = number_format($row->overdue_120_plus, 2, '.', ',');
            $val_total = number_format($row->total_overdue, 2, '.', ',');

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . $val_0_104 . '</td>
                <td class="number">' . $val_105_120 . '</td>
                <td class="number">' . $val_120_plus . '</td>
                <td class="number">' . $val_total . '</td>
            </tr>';
        }

        if (!empty($failedBranches)) {
            foreach ($branchOrder as $branchName) {
                $connection = ChartHelper::getBranchConnection($branchName);
                if (!$connection || !in_array($connection, $failedBranches, true)) {
                    continue;
                }

                $html .= '<tr>
                    <td>' . $no++ . '</td>
                    <td>' . htmlspecialchars($branchName) . '</td>
                    <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($branchName)) . '</td>
                    <td class="number">-</td>
                    <td class="number">-</td>
                    <td class="number">-</td>
                    <td class="number">Connection Failed</td>
                </tr>';
            }
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($total_0_104, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_105_120, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_120_plus, 2, '.', ',') . '</strong></td>
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
        // Tingkatkan batas waktu eksekusi untuk operasi export
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $currentDate = $request->input('current_date', now()->toDateString());
        $filter = 'all';

        $branchOrder = ChartHelper::getBranchOrder();
        $branchConnections = [];
        foreach ($branchOrder as $branchName) {
            $connection = ChartHelper::getBranchConnection($branchName);
            if ($connection) {
                $branchConnections[] = $connection;
            }
        }

        // Query dengan subquery korelasi untuk menghitung pembayaran per invoice
        $sql = "
        SELECT
            branch_name,
            SUM(CASE WHEN age >= 0 AND age <= 104 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_0_104,
            SUM(CASE WHEN age >= 105 AND age <= 120 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_105_120,
            SUM(CASE WHEN age > 120 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as overdue_120_plus,
            SUM(CASE WHEN age >= 0 AND totallines > (bayar * pengali)
                THEN (totallines - (bayar * pengali)) ELSE 0 END) as total_overdue
        FROM (
            SELECT
                inv.totallines,
                org.name as branch_name,
                -- Correlated subquery to get payment per invoice
                (
                    SELECT COALESCE(SUM(alocln.amount + alocln.writeoffamt + alocln.discountamt
                        + CASE WHEN SUBSTR(inv.documentno, 1, 3) = 'CNC' THEN alocln.overunderamt ELSE 0 END), 0)
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
                (? ::date - inv.dateinvoiced::date) as age
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
        WHERE totallines <> abs(bayar * pengali)
            AND age >= 0
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $allResults = collect();
        $failedBranches = [];

        foreach ($branchConnections as $connection) {
            // Lakukan pengecekan konektivitas ringan terlebih dahulu untuk menghindari hang lama
            if (!$this->canConnectToBranch($connection)) {
                Log::warning("Export PDF - Skipping {$connection} due to connectivity check failure");
                $failedBranches[] = $connection;
                continue;
            }

            try {
                // Timeout per koneksi 30 detik
                DB::connection($connection)->statement("SET statement_timeout = 30000");

                $branchResults = DB::connection($connection)->select($sql, [$currentDate, $currentDate, $currentDate]);
                $allResults = $allResults->merge($branchResults);

                DB::connection($connection)->statement("SET statement_timeout = 0");
            } catch (\Exception $e) {
                Log::warning("Export PDF - Failed to query {$connection}: " . $e->getMessage());
                $failedBranches[] = $connection;

                try {
                    DB::connection($connection)->statement("SET statement_timeout = 0");
                } catch (\Exception $resetError) {
                }

                continue;
            }
        }

        if (!empty($failedBranches)) {
            Log::info("Export PDF - Failed branches: " . implode(', ', $failedBranches));
        }

        $queryResult = $allResults->map(function ($item) {
            if ($item->branch_name === 'PT. Putra Mandiri Damai') {
                $item->branch_name = 'PWM Makassar';
            }
            if ($item->branch_name === 'PT. CIPTA ARDANA KENCANA') {
                $item->branch_name = 'MPM Tangerang';
            }

            $fields = ['overdue_0_104', 'overdue_105_120', 'overdue_120_plus', 'total_overdue'];
            foreach ($fields as $field) {
                $value = (float) ($item->{$field} ?? 0);
                $item->{$field} = (abs($value) < 1) ? 0.0 : $value;
            }

            return $item;
        });

        // Kecualikan cabang dengan anomali data (PWM Makassar, PWM Surabaya)
        $queryResult = $queryResult->reject(function ($item) {
            return in_array($item->branch_name, ['PWM Makassar', 'PWM Surabaya'], true);
        })->values();

        $queryResult = ChartHelper::sortByBranchOrder($queryResult, 'branch_name');

        $total_0_104 = $queryResult->sum('overdue_0_104');
        $total_105_120 = $queryResult->sum('overdue_105_120');
        $total_120_plus = $queryResult->sum('overdue_120_plus');
        $grandTotal = $queryResult->sum('total_overdue');

        $formattedDate = \Carbon\Carbon::parse($currentDate)->format('d F Y');
        $fileDate = \Carbon\Carbon::parse($currentDate)->format('d-m-Y');

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
                <div class="title">UMUR PIUTANG USAHA</div>
                <div class="date">As of ' . $formattedDate . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px; text-align: left;">NO</th>
                        <th style="width: 150px; text-align: left;">NAMA CABANG</th>
                        <th style="width: 70px; text-align: left;">KODE CABANG</th>
                        <th style="width: 120px; text-align: right;">0-104 DAYS</th>
                        <th style="width: 120px; text-align: right;">105-120 DAYS</th>
                        <th style="width: 120px; text-align: right;">&gt;120 DAYS</th>
                        <th style="width: 120px; text-align: right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>';

        $no = 1;
        foreach ($queryResult as $row) {
            $val_0_104 = number_format($row->overdue_0_104, 2, '.', ',');
            $val_105_120 = number_format($row->overdue_105_120, 2, '.', ',');
            $val_120_plus = number_format($row->overdue_120_plus, 2, '.', ',');
            $val_total = number_format($row->total_overdue, 2, '.', ',');

            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row->branch_name) . '</td>
                <td>' . htmlspecialchars(ChartHelper::getBranchAbbreviation($row->branch_name)) . '</td>
                <td class="number">' . $val_0_104 . '</td>
                <td class="number">' . $val_105_120 . '</td>
                <td class="number">' . $val_120_plus . '</td>
                <td class="number">' . $val_total . '</td>
            </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                        <td class="number"><strong>' . number_format($total_0_104, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_105_120, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($total_120_plus, 2, '.', ',') . '</strong></td>
                        <td class="number"><strong>' . number_format($grandTotal, 2, '.', ',') . '</strong></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <br>
            <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' . htmlspecialchars(Auth::user()->name) . ' (' . date('d/m/Y - H.i') . ' WIB)</div>
        </body>
        </html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Piutang_Usaha_' . $fileDate . '.pdf';

        return $pdf->download($filename);
    }
}

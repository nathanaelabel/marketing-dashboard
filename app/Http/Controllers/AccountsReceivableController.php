<?php

namespace App\Http\Controllers;

use App\Helpers\ChartHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsReceivableController extends Controller
{
    public function data(Request $request)
    {
        $currentDate = now()->toDateString();

        // Use CTE for better performance - pre-calculate payments in one pass
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
        FROM invoice_data
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $queryResult = collect(DB::select($sql, [$currentDate, $currentDate]))
            ->map(function ($item) {
                $item->overdue_1_30 = (float) $item->overdue_1_30;
                $item->overdue_31_60 = (float) $item->overdue_31_60;
                $item->overdue_61_90 = (float) $item->overdue_61_90;
                $item->overdue_90_plus = (float) $item->overdue_90_plus;
                $item->total_overdue = (float) $item->total_overdue;
                return $item;
            });

        $formattedData = ChartHelper::formatAccountsReceivableData($queryResult, $currentDate);

        return response()->json($formattedData);
    }

    public function exportExcel(Request $request)
    {
        $currentDate = now()->toDateString();

        // Use CTE for better performance - pre-calculate payments in one pass
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
        FROM invoice_data
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $queryResult = collect(DB::select($sql, [$currentDate, $currentDate]))
            ->map(function ($item) {
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
        $filename = 'Accounts_Receivable_' . $fileDate . '.xls';

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
                            <x:Name>Accounts Receivable</x:Name>
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
                body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }
                table { border-collapse: collapse; }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 4px 8px; 
                    text-align: left; 
                    font-size: 10pt;
                    white-space: nowrap;
                }
                th { 
                    background-color: #4CAF50; 
                    color: white; 
                    font-weight: bold; 
                    font-size: 10pt;
                }
                .title { font-size: 10pt; font-weight: bold; margin-bottom: 5px; }
                .date { font-size: 10pt; margin-bottom: 10px; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
                .number { text-align: right; }
                .col-no { width: 70px; }
                .col-branch { width: 250px; }
                .col-code { width: 160px; }
                .col-amount { width: 260px; }
            </style>
        </head>
        <body>
            <div class="title">Accounts Receivable Report</div>
            <div class="date">As of: ' . $formattedDate . '</div>
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
                        <th>No</th>
                        <th>Branch Name</th>
                        <th>Branch Code</th>
                        <th style="text-align: right;">1-30 Days (Rp)</th>
                        <th style="text-align: right;">31-60 Days (Rp)</th>
                        <th style="text-align: right;">61-90 Days (Rp)</th>
                        <th style="text-align: right;">&gt; 90 Days (Rp)</th>
                        <th style="text-align: right;">Total (Rp)</th>
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
        </body>
        </html>';

        return response($html, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $currentDate = now()->toDateString();

        // Use CTE for better performance - pre-calculate payments in one pass
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
        FROM invoice_data
        WHERE (totallines - (bayar * pengali)) <> 0
            AND age >= 1
        GROUP BY branch_name
        ORDER BY total_overdue DESC
        ";

        $queryResult = collect(DB::select($sql, [$currentDate, $currentDate]))
            ->map(function ($item) {
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
                    font-family: Arial, sans-serif; 
                    font-size: 9pt;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .title { 
                    font-size: 16pt; 
                    font-weight: bold; 
                    margin-bottom: 5px;
                }
                .date { 
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
                    padding: 6px; 
                    text-align: left;
                    font-size: 9pt;
                }
                th { 
                    background-color: rgba(38, 102, 241, 0.9); 
                    color: white; 
                    font-weight: bold;
                }
                .number { text-align: right; }
                .total-row { 
                    font-weight: bold; 
                    background-color: #f2f2f2;
                }
                .total-row td {
                    border-top: 2px solid #333;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Accounts Receivable Report</div>
                <div class="date">As of: ' . $formattedDate . '</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th style="width: 150px;">Branch Name</th>
                        <th style="width: 70px;">Code</th>
                        <th style="width: 100px; text-align: right;">1-30 Days</th>
                        <th style="width: 100px; text-align: right;">31-60 Days</th>
                        <th style="width: 100px; text-align: right;">61-90 Days</th>
                        <th style="width: 100px; text-align: right;">&gt; 90 Days</th>
                        <th style="width: 120px; text-align: right;">Total</th>
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
        </body>
        </html>';

        // Use DomPDF to generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        $filename = 'Accounts_Receivable_' . $fileDate . '.pdf';

        return $pdf->download($filename);
    }
}

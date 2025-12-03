<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\TableHelper;

class ReturnComparisonController extends Controller
{
    public function index()
    {
        return view("return-comparison");
    }

    public function getData(Request $request)
    {
        try {
            // Tingkatkan batas waktu eksekusi untuk query berat
            set_time_limit(180);
            $month = $request->get("month", date("n"));
            $year = $request->get("year", date("Y"));

            $validationErrors = TableHelper::validatePeriodParameters(
                $month,
                $year,
            );
            if (!empty($validationErrors)) {
                return response()->json(["error" => $validationErrors[0]], 400);
            }

            // Buat cache key berdasarkan bulan dan tahun
            $cacheKey = "return_comparison_{$year}_{$month}";

            // Cek apakah cache sudah ada - jika ada, langsung return tanpa query tambahan
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                Log::info("ReturnComparison: Cache hit for {$year}-{$month}, returning cached data");
            } else {
                // Cache tidak ada, ambil data baru
                Log::info("ReturnComparison: Cache miss for {$year}-{$month}, fetching fresh data");

                $branchMapping = TableHelper::getBranchMapping();

                $startTime = microtime(true);
                $salesBrutoData = $this->getAllSalesBruto($month, $year);
                $salesBrutoTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("ReturnComparison: getAllSalesBruto took {$salesBrutoTime}ms");

                $startTime = microtime(true);
                $cncData = $this->getAllCNCData($month, $year);
                $cncTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("ReturnComparison: getAllCNCData took {$cncTime}ms");

                $startTime = microtime(true);
                $barangData = $this->getAllBarangData($month, $year);
                $barangTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("ReturnComparison: getAllBarangData took {$barangTime}ms");

                $startTime = microtime(true);
                $cabangPabrikData = $this->getAllCabangPabrikData($month, $year);
                $cabangPabrikTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("ReturnComparison: getAllCabangPabrikData took {$cabangPabrikTime}ms");

                $totalTime = $salesBrutoTime + $cncTime + $barangTime + $cabangPabrikTime;
                Log::info("ReturnComparison: Total query time {$totalTime}ms for month={$month}, year={$year}");

                $cachedData = [
                    'salesBrutoData' => $salesBrutoData,
                    'cncData' => $cncData,
                    'barangData' => $barangData,
                    'cabangPabrikData' => $cabangPabrikData,
                    'branchMapping' => $branchMapping,
                ];

                // Durasi cache 24 jam
                $cacheDuration = 86400;

                Cache::put($cacheKey, $cachedData, $cacheDuration);
                Log::info("ReturnComparison: Data cached for {$year}-{$month} with duration 24 hours");
            }

            // Ekstrak data dari cache
            $salesBrutoData = $cachedData['salesBrutoData'];
            $cncData = $cachedData['cncData'];
            $barangData = $cachedData['barangData'];
            $cabangPabrikData = $cachedData['cabangPabrikData'];
            $branchMapping = $cachedData['branchMapping'];

            $data = [];
            $no = 1;

            $totals = [
                "sales_bruto_rp" => 0,
                "cnc_pc" => 0,
                "cnc_rp" => 0,
                "barang_pc" => 0,
                "barang_rp" => 0,
                "cabang_pabrik_pc" => 0,
                "cabang_pabrik_rp" => 0,
            ];

            // Iterasi setiap cabang dan gabungkan data
            foreach ($branchMapping as $branchName => $branchCode) {
                $salesBruto = $salesBrutoData[$branchName] ?? [
                    "pc" => 0,
                    "rp" => 0,
                ];
                $cnc = $cncData[$branchName] ?? ["pc" => 0, "rp" => 0];
                $barang = $barangData[$branchName] ?? ["pc" => 0, "rp" => 0];
                $cabangPabrik = $cabangPabrikData[$branchName] ?? [
                    "pc" => 0,
                    "rp" => 0,
                ];

                $cncPercent =
                    $salesBruto["rp"] > 0
                    ? ($cnc["rp"] / $salesBruto["rp"]) * 100
                    : 0;
                $barangPercent =
                    $salesBruto["rp"] > 0
                    ? ($barang["rp"] / $salesBruto["rp"]) * 100
                    : 0;
                $cabangPabrikPercent =
                    $salesBruto["rp"] > 0
                    ? ($cabangPabrik["rp"] / $salesBruto["rp"]) * 100
                    : 0;

                $data[] = [
                    "no" => $no++,
                    "branch_code" => $branchCode,
                    "branch_name" => $branchName,
                    "sales_bruto_pc" => $salesBruto["pc"],
                    "sales_bruto_rp" => $salesBruto["rp"],
                    "cnc_pc" => $cnc["pc"],
                    "cnc_rp" => $cnc["rp"],
                    "cnc_percent" => $cncPercent,
                    "barang_pc" => $barang["pc"],
                    "barang_rp" => $barang["rp"],
                    "barang_percent" => $barangPercent,
                    "cabang_pabrik_pc" => $cabangPabrik["pc"],
                    "cabang_pabrik_rp" => $cabangPabrik["rp"],
                    "cabang_pabrik_percent" => $cabangPabrikPercent,
                ];

                $totals["sales_bruto_rp"] += $salesBruto["rp"];
                $totals["cnc_pc"] += $cnc["pc"];
                $totals["cnc_rp"] += $cnc["rp"];
                $totals["barang_pc"] += $barang["pc"];
                $totals["barang_rp"] += $barang["rp"];
                $totals["cabang_pabrik_pc"] += $cabangPabrik["pc"];
                $totals["cabang_pabrik_rp"] += $cabangPabrik["rp"];
            }

            // Hitung persentase total
            $totals["cnc_percent"] = $totals["sales_bruto_rp"] > 0
                ? ($totals["cnc_rp"] / $totals["sales_bruto_rp"]) * 100
                : 0;
            $totals["barang_percent"] = $totals["sales_bruto_rp"] > 0
                ? ($totals["barang_rp"] / $totals["sales_bruto_rp"]) * 100
                : 0;
            $totals["cabang_pabrik_percent"] = $totals["sales_bruto_rp"] > 0
                ? ($totals["cabang_pabrik_rp"] / $totals["sales_bruto_rp"]) * 100
                : 0;

            $period = TableHelper::formatPeriodInfo($month, $year);

            return response()->json([
                "data" => $data,
                "totals" => $totals,
                "period" => $period,
                "total_count" => count($data),
            ]);
        } catch (\Exception $e) {
            TableHelper::logError("ReturnComparisonController", "getData", $e, [
                "month" => $request->get("month"),
                "year" => $request->get("year"),
                "message" => $e->getMessage(),
                "line" => $e->getLine(),
            ]);

            return TableHelper::errorResponse(
                "Failed to load return comparison data: " . $e->getMessage(),
            );
        }
    }

    private function getAllSalesBruto($month, $year)
    {
        $results = DB::select('SELECT * FROM sp_get_return_comparison_sales_bruto(?, ?)', [$year, $month]);

        $data = [];
        foreach ($results as $result) {
            $data[$result->branch_name] = [
                "pc" => 0,
                "rp" => (float) $result->total_rp,
            ];
        }

        return $data;
    }

    private function getAllCNCData($month, $year)
    {
        $results = DB::select('SELECT * FROM sp_get_return_comparison_cnc(?, ?)', [$year, $month]);

        $data = [];
        foreach ($results as $result) {
            $data[$result->branch_name] = [
                "pc" => (float) $result->total_qty,
                "rp" => (float) $result->total_rp,
            ];
        }

        return $data;
    }

    private function getAllBarangData($month, $year)
    {
        $results = DB::select('SELECT * FROM sp_get_return_comparison_barang(?, ?)', [$year, $month]);

        $data = [];
        foreach ($results as $result) {
            $data[$result->branch_name] = [
                "pc" => (float) $result->total_qty,
                "rp" => (float) $result->total_nominal,
            ];
        }

        return $data;
    }

    private function getAllCabangPabrikData($month, $year)
    {
        $results = DB::select('SELECT * FROM sp_get_return_comparison_cabang_pabrik(?, ?)', [$year, $month]);

        $data = [];
        foreach ($results as $result) {
            $data[$result->branch_name] = [
                "pc" => (float) $result->total_qty,
                "rp" => (float) $result->total_nominal,
            ];
        }

        return $data;
    }

    public function exportExcel(Request $request)
    {
        try {
            $month = $request->get("month", date("n"));
            $year = $request->get("year", date("Y"));

            $validationErrors = TableHelper::validatePeriodParameters(
                $month,
                $year,
            );
            if (!empty($validationErrors)) {
                return response()->json(["error" => $validationErrors[0]], 400);
            }

            $dataRequest = new Request(["month" => $month, "year" => $year]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData["error"])) {
                return response()->json(
                    ["error" => "Failed to fetch data"],
                    500,
                );
            }

            $data = $responseData["data"];
            $totals = $responseData["totals"] ?? null;
            $period = $responseData["period"];

            $filename =
                "Perbandingan_Retur_Rusak_" .
                str_replace(" ", "_", $period["month_name"] . "_" . $year) .
                ".xls";

            $headers = [
                "Content-Type" => "application/vnd.ms-excel",
                "Content-Disposition" =>
                'attachment; filename="' . $filename . '"',
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0",
            ];

            $html =
                '
            <html xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Perbandingan Retur Rusak</x:Name>
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
                    .period {
                        font-family: Verdana, sans-serif;
                        font-size: 12pt;
                        margin-bottom: 15px;
                    }
                    .number { text-align: right; }
                </style>
            </head>
            <body>
                <div class="title">PERBANDINGAN RETUR RUSAK</div>
                <div class="period">Periode ' .
                htmlspecialchars($period["date_range"] ?? ($period["month_name"] . " " . $year)) .
                '</div>
                <br>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 60px; text-align: center;">NO</th>
                            <th rowspan="2" style="width: 150px; text-align: center;">CABANG</th>
                            <th colspan="1" style="width: 200px; text-align: center;">SALES BRUTO</th>
                            <th colspan="3" style="width: 300px; text-align: center;">CUST. KE CABANG (CNC) (FX009)</th>
                            <th colspan="3" style="width: 300px; text-align: center;">CUST. KE CABANG (BARANG) (FX016)</th>
                            <th colspan="3" style="width: 300px; text-align: center;">CABANG KE PABRIK</th>
                        </tr>
                        <tr>
                            <th style="width: 300px; text-align: center;">RP</th>
                            <th style="width: 150px; text-align: center;">PC</th>
                            <th style="width: 300px; text-align: center;">RP</th>
                            <th style="width: 120px; text-align: center;">%</th>
                            <th style="width: 150px; text-align: center;">PC</th>
                            <th style="width: 300px; text-align: center;">RP</th>
                            <th style="width: 120px; text-align: center;">%</th>
                            <th style="width: 150px; text-align: center;">PC</th>
                            <th style="width: 300px; text-align: center;">RP</th>
                            <th style="width: 120px; text-align: center;">%</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .=
                    '<tr>
                    <td style="text-align: right;">' .
                    $item["no"] .
                    '</td>
                    <td>' .
                    htmlspecialchars($item["branch_code"]) .
                    '</td>
                    <td class="number">' .
                    ($item["sales_bruto_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $item["sales_bruto_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_pc"] == 0
                        ? "-"
                        : number_format($item["cnc_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_rp"] == 0
                        ? "-"
                        : "Rp " . number_format($item["cnc_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_percent"] == 0
                        ? "-"
                        : number_format($item["cnc_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($item["barang_pc"] == 0
                        ? "-"
                        : number_format($item["barang_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["barang_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format($item["barang_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["barang_percent"] == 0
                        ? "-"
                        : number_format($item["barang_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_pc"] == 0
                        ? "-"
                        : number_format(
                            $item["cabang_pabrik_pc"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $item["cabang_pabrik_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_percent"] == 0
                        ? "-"
                        : number_format(
                            $item["cabang_pabrik_percent"],
                            2,
                            ".",
                            ",",
                        ) . "%") .
                    '</td>
                </tr>';
            }

            if ($totals) {
                $html .=
                    '<tr style="background-color: #F0F0F0; font-weight: bold; border-top: 2px solid #000;">
                    <td colspan="2" style="text-align: center;">TOTAL</td>
                    <td class="number">' .
                    ($totals["sales_bruto_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $totals["sales_bruto_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_pc"] == 0
                        ? "-"
                        : number_format($totals["cnc_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_rp"] == 0
                        ? "-"
                        : "Rp " . number_format($totals["cnc_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_percent"] == 0
                        ? "-"
                        : number_format($totals["cnc_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_pc"] == 0
                        ? "-"
                        : number_format($totals["barang_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format($totals["barang_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_percent"] == 0
                        ? "-"
                        : number_format($totals["barang_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_pc"] == 0
                        ? "-"
                        : number_format(
                            $totals["cabang_pabrik_pc"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $totals["cabang_pabrik_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_percent"] == 0
                        ? "-"
                        : number_format(
                            $totals["cabang_pabrik_percent"],
                            2,
                            ".",
                            ",",
                        ) . "%") .
                    '</td>
                </tr>';
            }

            $html .=
                '
                    </tbody>
                </table>
                <br>
                <br>
                <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' .
                htmlspecialchars(Auth::user()->name) .
                " (" .
                date("d/m/Y - H.i") .
                ' WIB)</div>
            </body>
            </html>';

            return response($html, 200, $headers);
        } catch (\Exception $e) {
            TableHelper::logError(
                "ReturnComparisonController",
                "exportExcel",
                $e,
                [
                    "month" => $request->get("month"),
                    "year" => $request->get("year"),
                ],
            );

            return response()->json(
                ["error" => "Failed to export Excel file"],
                500,
            );
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $month = $request->get("month", date("n"));
            $year = $request->get("year", date("Y"));

            $validationErrors = TableHelper::validatePeriodParameters(
                $month,
                $year,
            );
            if (!empty($validationErrors)) {
                return response()->json(["error" => $validationErrors[0]], 400);
            }

            $dataRequest = new Request(["month" => $month, "year" => $year]);
            $dataResponse = $this->getData($dataRequest);
            $responseData = json_decode($dataResponse->getContent(), true);

            if (isset($responseData["error"])) {
                return response()->json(
                    ["error" => "Failed to fetch data"],
                    500,
                );
            }

            $data = $responseData["data"];
            $totals = $responseData["totals"] ?? null;
            $period = $responseData["period"];

            // Create HTML for PDF
            $html =
                '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    @page { margin: 20px; }
                    body {
                        font-family: Verdana, sans-serif;
                        font-size: 7pt;
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
                    .period {
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
                        padding: 4px 6px;
                        text-align: left;
                        font-family: Verdana, sans-serif;
                        font-size: 6pt;
                    }
                    th {
                        background-color: #F5F5F5;
                        color: #000;
                        font-weight: bold;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .number { text-align: right; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="title">PERBANDINGAN RETUR RUSAK</div>
                    <div class="period">Periode ' .
                htmlspecialchars($period["date_range"] ?? ($period["month_name"] . " " . $year)) .
                '</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 30px;">NO</th>
                            <th rowspan="2" style="width: 80px;">CABANG</th>
                            <th colspan="1" style="width: 100px;">SALES BRUTO</th>
                            <th colspan="3" style="width: 150px;">CUST. KE CABANG (CNC) (FX009)</th>
                            <th colspan="3" style="width: 150px;">CUST. KE CABANG (BARANG) (FX016)</th>
                            <th colspan="3" style="width: 150px;">CABANG KE PABRIK</th>
                        </tr>
                        <tr>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                            <th style="width: 50px;">PC</th>
                            <th style="width: 70px;">RP</th>
                            <th style="width: 40px;">%</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($data as $item) {
                $html .=
                    '<tr>
                    <td style="text-align: right;">' .
                    $item["no"] .
                    '</td>
                    <td>' .
                    htmlspecialchars($item["branch_code"]) .
                    '</td>
                    <td class="number">' .
                    ($item["sales_bruto_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $item["sales_bruto_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_pc"] == 0
                        ? "-"
                        : number_format($item["cnc_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_rp"] == 0
                        ? "-"
                        : "Rp " . number_format($item["cnc_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["cnc_percent"] == 0
                        ? "-"
                        : number_format($item["cnc_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($item["barang_pc"] == 0
                        ? "-"
                        : number_format($item["barang_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["barang_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format($item["barang_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($item["barang_percent"] == 0
                        ? "-"
                        : number_format($item["barang_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_pc"] == 0
                        ? "-"
                        : number_format(
                            $item["cabang_pabrik_pc"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $item["cabang_pabrik_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($item["cabang_pabrik_percent"] == 0
                        ? "-"
                        : number_format(
                            $item["cabang_pabrik_percent"],
                            2,
                            ".",
                            ",",
                        ) . "%") .
                    '</td>
                </tr>';
            }

            // Add TOTAL row for PDF export
            if ($totals) {
                $html .=
                    '<tr style="background-color: #F0F0F0; font-weight: bold; border-top: 2px solid #000;">
                    <td colspan="2" style="text-align: center;">TOTAL</td>
                    <td class="number">' .
                    ($totals["sales_bruto_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $totals["sales_bruto_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_pc"] == 0
                        ? "-"
                        : number_format($totals["cnc_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_rp"] == 0
                        ? "-"
                        : "Rp " . number_format($totals["cnc_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["cnc_percent"] == 0
                        ? "-"
                        : number_format($totals["cnc_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_pc"] == 0
                        ? "-"
                        : number_format($totals["barang_pc"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format($totals["barang_rp"], 0, ".", ",")) .
                    '</td>
                    <td class="number">' .
                    ($totals["barang_percent"] == 0
                        ? "-"
                        : number_format($totals["barang_percent"], 2, ".", ",") .
                        "%") .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_pc"] == 0
                        ? "-"
                        : number_format(
                            $totals["cabang_pabrik_pc"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_rp"] == 0
                        ? "-"
                        : "Rp " .
                        number_format(
                            $totals["cabang_pabrik_rp"],
                            0,
                            ".",
                            ",",
                        )) .
                    '</td>
                    <td class="number">' .
                    ($totals["cabang_pabrik_percent"] == 0
                        ? "-"
                        : number_format(
                            $totals["cabang_pabrik_percent"],
                            2,
                            ".",
                            ",",
                        ) . "%") .
                    '</td>
                </tr>';
            }

            $html .=
                '
                    </tbody>
                </table>
                <br>
                <br>
                <div style="font-family: Verdana, sans-serif; font-size: 8pt; font-style: italic;">' .
                htmlspecialchars(Auth::user()->name) .
                " (" .
                date("d/m/Y - H.i") .
                ' WIB)</div>
            </body>
            </html>';

            // Use DomPDF to generate PDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper("A4", "landscape");

            $filename =
                "Perbandingan_Retur_Rusak_" .
                str_replace(" ", "_", $period["month_name"] . "_" . $year) .
                ".pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            TableHelper::logError(
                "ReturnComparisonController",
                "exportPdf",
                $e,
                [
                    "month" => $request->get("month"),
                    "year" => $request->get("year"),
                ],
            );

            return response()->json(
                ["error" => "Failed to export PDF file"],
                500,
            );
        }
    }

    public function clearCache(Request $request)
    {
        try {
            $month = $request->get("month");
            $year = $request->get("year");

            if ($month && $year) {
                $cacheKey = "return_comparison_{$year}_{$month}";
                Cache::forget($cacheKey);

                return response()->json([
                    "success" => true,
                    "message" => "Cache cleared for {$month}/{$year}",
                ]);
            } else {
                // Gunakan H-1 karena dashboard diupdate setiap malam
                $clearedKeys = [];
                $currentDate = now();

                for ($i = 0; $i < 12; $i++) {
                    $date = $currentDate->copy()->subMonths($i);
                    $cacheKey = "return_comparison_{$date->year}_{$date->month}";
                    if (Cache::has($cacheKey)) {
                        Cache::forget($cacheKey);
                        $clearedKeys[] = $cacheKey;
                    }
                }

                return response()->json([
                    "success" => true,
                    "message" => "Cleared " . count($clearedKeys) . " cache entries",
                    "keys" => $clearedKeys,
                ]);
            }
        } catch (\Exception $e) {
            TableHelper::logError(
                "ReturnComparisonController",
                "clearCache",
                $e,
                [
                    "month" => $request->get("month"),
                    "year" => $request->get("year"),
                ],
            );

            return response()->json(
                ["error" => "Failed to clear cache"],
                500,
            );
        }
    }
}

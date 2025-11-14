<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class ChartHelper
{
    /**
     * Determines Y-axis configuration based on the maximum value.
     *
     * @param float $maxValue The maximum value in the dataset.
     * @param ?float $averageRelevantValue Optional average of relevant (e.g., non-zero) values in the dataset.
     * @return array An array containing 'label', 'divisor', and 'unit'.
     */
    public static function getYAxisConfig(float $maxValue, ?float $averageRelevantValue = null, array $allDataValues = []): array
    {
        $billionThreshold = 1e9; // 1 Billion
        $forceVeryHighBillionsThreshold = 2 * 1e9; // e.g., if max is 2B or more, definitely use Billions
        $forceMillionLowThreshold = 2 * 1e6; // Values below this (e.g., 2M) will definitely use Millions scale
        $significantSmallerValueThreshold = 0.7 * 1e9; // e.g., 700M. If a bar is below this, it's a 'smaller' significant value.

        if ($maxValue < $forceMillionLowThreshold) {
            return ['label' => 'Juta Rupiah', 'divisor' => 1e6, 'unit' => 'Jt'];
        }

        // If maxValue is very high, force Billions
        if ($maxValue >= $forceVeryHighBillionsThreshold) {
            return ['label' => 'Miliar Rupiah', 'divisor' => 1e9, 'unit' => 'M'];
        }

        // If maxValue is between 1B (inclusive) and forceVeryHighBillionsThreshold (exclusive)
        if ($maxValue >= $billionThreshold) {
            $preferMillions = false;
            if (!empty($allDataValues)) {
                $nonZeroValues = array_filter($allDataValues, fn($v) => $v > 0.001 * $billionThreshold);
                if (count($nonZeroValues) > 1) {
                    foreach ($nonZeroValues as $value) {
                        // If there's any significant bar that's considerably smaller than 1B (e.g. < 700M)
                        // then prefer Millions to keep that bar's value readable.
                        if ($value < $significantSmallerValueThreshold) {
                            $preferMillions = true;
                            break;
                        }
                    }
                }
            }

            if ($preferMillions) {
                return ['label' => 'Juta Rupiah', 'divisor' => 1e6, 'unit' => 'Jt'];
            }
            // Otherwise (maxValue is 1B up to $forceVeryHighBillionsThreshold and no significantly smaller bars, or only one bar), use Billions
            return ['label' => 'Miliar Rupiah', 'divisor' => 1e9, 'unit' => 'M'];
        }

        // Default: maxValue is < 1B (and >= $forceMillionLowThreshold)
        return ['label' => 'Juta Rupiah', 'divisor' => 1e6, 'unit' => 'Jt'];
    }

    /**
     * Formats a number into a compact representation (e.g., 23M, 1.2B, 500K).
     *
     * @param float $value The value to format.
     * @param int $precision The number of decimal places for the compact form.
     * @return string The formatted compact number string.
     */
    public static function formatNumberForDisplay(float $value, int $precision = 1): string
    {
        if ($value >= 1e9) {
            return round($value / 1e9, $precision) . 'M';
        }
        if ($value >= 1e6) {
            return round($value / 1e6, $precision) . 'Jt';
        }
        if ($value >= 1e3) {
            return round($value / 1e3, $precision) . 'rb';
        }
        return (string)round($value, $precision);
    }

    /**
     * Formats a number as full Indonesian Rupiah currency.
     *
     * @param float $value The value to format.
     * @return string The formatted currency string.
     */
    public static function formatFullCurrency(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    /**
     * Calculates a suggested maximum for a Chart.js Y-axis based on data.
     *
     * @param float $maxDataValue The actual maximum data point in the dataset.
     * @param float $divisor The divisor being used for the axis (e.g., 1e6 for millions, 1e9 for billions).
     * @param float $paddingFactor Padding factor to apply above the maxDataValue (e.g., 1.2 for 20% padding).
     * @param float $minSuggestedMaxDivisorUnits Minimum suggested max in terms of divisor units (e.g., 5 means 5M or 5B if data is very small or zero).
     * @return float The suggested maximum value for the Y-axis, in raw unscaled units.
     */
    public static function calculateSuggestedMax(
        float $maxDataValue,
        float $divisor,
        float $paddingFactor = 1.0,
        float $minSuggestedMaxDivisorUnits = 5.0
    ): float {
        if ($maxDataValue <= 0) {
            return $minSuggestedMaxDivisorUnits * $divisor;
        }

        // Calculate the scaled value (e.g., if maxDataValue is 6.5M and divisor is 1e9, scaledValue = 6.5)
        $scaledValue = $maxDataValue / $divisor;

        // For very small values (<= 5), just round up to 5
        if ($scaledValue <= 5) {
            return 5 * $divisor;
        }

        $padding = 0;
        $roundedUp = 0;

        if ($scaledValue <= 10) {
            // For values <= 10, round to next integer, then add 1-2 units
            // Example: 6.5 -> round to 7 -> add 1 = 8
            // Example: 8.0 -> round to 8 -> add 2 = 10
            $roundedUp = ceil($scaledValue);
            if ($scaledValue <= 7.5) {
                $padding = 1;
            } else {
                $padding = 2;
            }
        } elseif ($scaledValue <= 20) {
            $roundedUp = ceil($scaledValue);
            $padding = 2;
        } elseif ($scaledValue <= 50) {
            // For values 20-50, round to next 2, then add 3-5 units
            // Example: 34 -> round to 34 -> add 5 = 39, then round to 40
            $roundedUp = ceil($scaledValue / 2) * 2;
            $padding = 3;
        } elseif ($scaledValue <= 100) {
            // For values 50-100, round to next 5, then add 5 units
            // Example: 75 -> round to 75 -> add 5 = 80
            $roundedUp = ceil($scaledValue / 5) * 5;
            $padding = 5;
        } else {
            // For larger values, round to next 10, then add 10 units
            $roundedUp = ceil($scaledValue / 10) * 10;
            $padding = 10;
        }

        $suggestedMaxScaled = $roundedUp + $padding;

        // Round to nearest nice number for cleaner axis labels
        if ($suggestedMaxScaled <= 10) {
            // Round to nearest integer for values <= 10
            $suggestedMaxScaled = ceil($suggestedMaxScaled);
        } elseif ($suggestedMaxScaled <= 20) {
            // Round to nearest integer for values 10-20
            $suggestedMaxScaled = ceil($suggestedMaxScaled);
        } elseif ($suggestedMaxScaled <= 50) {
            // Round to nearest 2 for values 20-50
            $suggestedMaxScaled = ceil($suggestedMaxScaled / 2) * 2;
        } else {
            // Round to nearest 5 for larger values
            $suggestedMaxScaled = ceil($suggestedMaxScaled / 5) * 5;
        }

        $suggestedMax = $suggestedMaxScaled * $divisor;

        $minimumSensibleMax = $minSuggestedMaxDivisorUnits * $divisor;
        if ($suggestedMax < $minimumSensibleMax) {
            return $minimumSensibleMax;
        }

        return $suggestedMax;
    }

    public static function formatAccountsReceivableData($data, $currentDate, $filter = 'overdue', $branchOrder = [], $failedBranches = [], $anomalyBranches = [])
    {
        // Create a mapping of branch connection to abbreviation
        $connectionToAbbr = [
            'pgsql_trg' => 'TGR',
            'pgsql_bks' => 'BKS',
            'pgsql_jkt' => 'JKT',
            'pgsql_ptk' => 'PTK',
            'pgsql_lmp' => 'LMP',
            'pgsql_bjm' => 'BJM',
            'pgsql_crb' => 'CRB',
            'pgsql_bdg' => 'BDG',
            'pgsql_mks' => 'MKS',
            'pgsql_sby' => 'SBY',
            'pgsql_smg' => 'SMG',
            'pgsql_pwt' => 'PWT',
            'pgsql_dps' => 'DPS',
            'pgsql_plb' => 'PLB',
            'pgsql_pdg' => 'PDG',
            'pgsql_mdn' => 'MDN',
            'pgsql_pku' => 'PKU',
        ];

        // Create a mapping of branch name to data
        $dataByBranchName = $data->keyBy('branch_name');

        // Initialize ordered arrays
        $orderedLabels = [];
        $orderedData = [];

        // Process branches in the specified order
        foreach ($branchOrder as $connection) {
            $abbr = $connectionToAbbr[$connection] ?? strtoupper(str_replace('pgsql_', '', $connection));
            $orderedLabels[] = $abbr;

            // Check if this branch has anomaly
            if (in_array($connection, $anomalyBranches)) {
                // Add anomaly placeholder data
                if ($filter === 'all') {
                    $orderedData[] = [
                        'range_0_104' => null,
                        'range_105_120' => null,
                        'range_120_plus' => null,
                        'total_overdue' => 0,
                        'is_offline' => false,
                        'is_anomaly' => true,
                        'is_connection_failed' => false,
                    ];
                } else {
                    $orderedData[] = [
                        'range_105_120' => null,
                        'range_120_plus' => null,
                        'total_overdue' => 0,
                        'is_offline' => false,
                        'is_anomaly' => true,
                        'is_connection_failed' => false,
                    ];
                }
            } elseif (in_array($connection, $failedBranches)) {
                // Add connection failed placeholder data
                if ($filter === 'all') {
                    $orderedData[] = [
                        'range_0_104' => null,
                        'range_105_120' => null,
                        'range_120_plus' => null,
                        'total_overdue' => 0,
                        'is_offline' => false,
                        'is_anomaly' => false,
                        'is_connection_failed' => true,
                    ];
                } else {
                    $orderedData[] = [
                        'range_105_120' => null,
                        'range_120_plus' => null,
                        'total_overdue' => 0,
                        'is_offline' => false,
                        'is_anomaly' => false,
                        'is_connection_failed' => true,
                    ];
                }
            } else {
                // Find matching data by branch name
                $branchData = null;
                foreach ($dataByBranchName as $branchName => $item) {
                    $itemAbbr = self::getBranchAbbreviation($branchName);
                    if ($itemAbbr === $abbr) {
                        $branchData = $item;
                        break;
                    }
                }

                if ($branchData) {
                    if ($filter === 'all') {
                        $orderedData[] = [
                            'range_0_104' => $branchData->range_0_104 ?? 0,
                            'range_105_120' => $branchData->range_105_120 ?? 0,
                            'range_120_plus' => $branchData->range_120_plus ?? 0,
                            'total_overdue' => $branchData->total_overdue ?? 0,
                            'is_offline' => false,
                            'is_anomaly' => false,
                            'is_connection_failed' => false,
                        ];
                    } else {
                        $orderedData[] = [
                            'range_105_120' => $branchData->range_105_120 ?? 0,
                            'range_120_plus' => $branchData->range_120_plus ?? 0,
                            'total_overdue' => $branchData->total_overdue ?? 0,
                            'is_offline' => false,
                            'is_anomaly' => false,
                            'is_connection_failed' => false,
                        ];
                    }
                } else {
                    // Branch has no data (might have failed query)
                    if ($filter === 'all') {
                        $orderedData[] = [
                            'range_0_104' => null,
                            'range_105_120' => null,
                            'range_120_plus' => null,
                            'total_overdue' => 0,
                            'is_offline' => false,
                            'is_anomaly' => false,
                            'is_connection_failed' => true,
                        ];
                    } else {
                        $orderedData[] = [
                            'range_105_120' => null,
                            'range_120_plus' => null,
                            'total_overdue' => 0,
                            'is_offline' => false,
                            'is_anomaly' => false,
                            'is_connection_failed' => true,
                        ];
                    }
                }
            }
        }

        // Calculate total (only from online branches)
        $totalOverdue = collect($orderedData)
            ->where('is_offline', false)
            ->sum('total_overdue');

        // Build datasets based on filter type
        if ($filter === 'all') {
            // All: Show 0-104 Days (green), 105-120 Days (yellow), >120 Days (red)
            $datasets = [
                [
                    'label' => '0 - 104 Hari',
                    'data' => collect($orderedData)->pluck('range_0_104')->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)', // Green
                    'borderRadius' => 5,
                ],
                [
                    'label' => '105 - 120 Hari',
                    'data' => collect($orderedData)->pluck('range_105_120')->all(),
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)', // Yellow
                    'borderRadius' => 5,
                ],
                [
                    'label' => '> 120 Hari',
                    'data' => collect($orderedData)->pluck('range_120_plus')->all(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)', // Red
                    'borderRadius' => 5,
                ],
            ];
        } else {
            // Overdue: Show only 105-120 Days (yellow) and >120 Days (red)
            $datasets = [
                [
                    'label' => '105 - 120 Hari',
                    'data' => collect($orderedData)->pluck('range_105_120')->all(),
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)', // Yellow
                    'borderRadius' => 5,
                ],
                [
                    'label' => '> 120 Hari',
                    'data' => collect($orderedData)->pluck('range_120_plus')->all(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)', // Red
                    'borderRadius' => 5,
                ],
            ];
        }

        // Calculate max value from online branches for special bar sizing
        $maxValue = 0;
        foreach ($orderedData as $item) {
            if (!$item['is_offline'] && !$item['is_anomaly'] && !$item['is_connection_failed']) {
                if ($filter === 'all') {
                    $total = ($item['range_0_104'] ?? 0) + ($item['range_105_120'] ?? 0) + ($item['range_120_plus'] ?? 0);
                } else {
                    $total = ($item['range_105_120'] ?? 0) + ($item['range_120_plus'] ?? 0);
                }
                $maxValue = max($maxValue, $total);
            }
        }

        // Add ANOMALY dataset (displayed as orange striped pattern)
        // Use 50% of max value for visibility
        $anomalyBarValue = max($maxValue * 0.5, 50000000);
        $anomalyData = [];
        foreach ($orderedData as $item) {
            $anomalyData[] = $item['is_anomaly'] ? $anomalyBarValue : null;
        }

        $datasets[] = [
            'label' => 'ANOMALY',
            'data' => $anomalyData,
            'backgroundColor' => 'rgba(251, 146, 60, 0.3)', // Orange with transparency
            'borderColor' => 'rgba(249, 115, 22, 0.8)', // Darker orange border
            'borderWidth' => 2,
            'borderRadius' => 5,
            'borderDash' => [5, 5], // Dashed border pattern
            'datalabels' => [
                'display' => true,
                'color' => 'rgba(194, 65, 12, 0.9)',
                'font' => [
                    'weight' => 'bold',
                    'size' => 14,
                ],
                'rotation' => -90, // Rotate text vertically
            ],
        ];

        // Add Connection Failed dataset (displayed as gray striped pattern)
        // Use 50% of max value for visibility
        $connectionFailedBarValue = max($maxValue * 0.5, 50000000);
        $connectionFailedData = [];
        foreach ($orderedData as $item) {
            $connectionFailedData[] = $item['is_connection_failed'] ? $connectionFailedBarValue : null;
        }

        $datasets[] = [
            'label' => 'Connection Failed',
            'data' => $connectionFailedData,
            'backgroundColor' => 'rgba(156, 163, 175, 0.3)', // Gray with transparency
            'borderColor' => 'rgba(107, 114, 128, 0.8)', // Darker gray border
            'borderWidth' => 2,
            'borderRadius' => 5,
            'borderDash' => [5, 5], // Dashed border pattern
            'datalabels' => [
                'display' => true,
                'color' => 'rgba(75, 85, 99, 0.9)',
                'font' => [
                    'weight' => 'bold',
                    'size' => 14,
                ],
                'rotation' => -90, // Rotate text vertically
            ],
        ];

        return [
            'labels' => $orderedLabels,
            'datasets' => $datasets,
            'total' => 'Rp ' . number_format($totalOverdue, 0, ',', '.'),
            'date' => \Carbon\Carbon::parse($currentDate)->format('l, d F Y'),
            'filter' => $filter,
            'anomalyBranches' => collect($orderedData)->map(function ($item, $index) use ($orderedLabels) {
                return $item['is_anomaly'] ? $orderedLabels[$index] : null;
            })->filter()->values()->all(),
            'connectionFailedBranches' => collect($orderedData)->map(function ($item, $index) use ($orderedLabels) {
                return $item['is_connection_failed'] ? $orderedLabels[$index] : null;
            })->filter()->values()->all(),
        ];
    }

    public static function getBranchAbbreviation(string $branchName): string
    {
        $abbreviations = [
            'MPM Tangerang' => 'TGR',
            'PWM Bekasi' => 'BKS',
            'PWM Jakarta' => 'JKT',
            'PWM Pontianak' => 'PTK',
            'PWM Lampung' => 'LMP',
            'PWM Banjarmasin' => 'BJM',
            'PWM Cirebon' => 'CRB',
            'PWM Bandung' => 'BDG',
            'PWM Makassar' => 'MKS',
            'PWM Surabaya' => 'SBY',
            'PWM Semarang' => 'SMG',
            'PWM Purwokerto' => 'PWT',
            'PWM Denpasar' => 'DPS',
            'PWM Palembang' => 'PLB',
            'PWM Padang' => 'PDG',
            'PWM Medan' => 'MDN',
            'PWM Pekanbaru' => 'PKU',
            'PT. Putra Mandiri Damai' => 'MKS',
            'PT. CIPTA ARDANA KENCANA' => 'TGR',
        ];

        // Return the abbreviation if found, otherwise return the original name
        return $abbreviations[$branchName] ?? $branchName;
    }

    /**
     * Get category color mapping for consistent chart colors
     */
    public static function getCategoryColors(): array
    {
        return [
            'MIKA' => 'rgb(81, 178, 243)',
            'AKSESORIS' => 'rgba(22, 220, 160, 0.8)',
            'CAT' => 'rgba(139, 92, 246, 0.8)',
            'SPARE PART' => 'rgba(244, 63, 94, 0.8)',
            'PRODUCT IMPORT' => 'rgba(241, 92, 246, 0.8)',
        ];
    }

    /**
     * Format date range for display
     */
    public static function formatDateRange(string $startDate, string $endDate): string
    {
        $formattedStart = \Carbon\Carbon::parse($startDate)->format('j M Y');
        $formattedEnd = \Carbon\Carbon::parse($endDate)->format('j M Y');
        return $formattedStart . ' - ' . $formattedEnd;
    }

    /**
     * Get standard invoice query filters
     */
    public static function getStandardInvoiceFilters(): array
    {
        return [
            'h.ad_client_id' => 1000001,
            'h.issotrx' => 'Y',
            'h.isactive' => 'Y'
        ];
    }

    /**
     * Build accounts receivable aging query with subqueries (sudah tidak dipakai untuk range aging ini)
     */
    public static function buildAccountsReceivableQuery(string $locationFilter = null): array
    {
        $paymentsSubquery = "
            SELECT
                c_invoice_id,
                SUM(amount + discountamt + writeoffamt) as paidamt
            FROM c_allocationline
            GROUP BY c_invoice_id
        ";

        $overdueQuery = "
            SELECT
                inv.ad_org_id,
                inv.grandtotal - COALESCE(p.paidamt, 0) as open_amount,
                DATE_PART('day', NOW() - inv.dateinvoiced::date) as age
            FROM c_invoice as inv
            LEFT JOIN ({$paymentsSubquery}) as p ON inv.c_invoice_id = p.c_invoice_id
            WHERE inv.issotrx = 'Y'
            AND inv.docstatus = 'CO'
            AND (inv.grandtotal - COALESCE(p.paidamt, 0)) > 0.01
        ";

        $mainQuery = "
            SELECT
                org.name as branch_name,
                SUM(CASE WHEN overdue.age BETWEEN 1 AND 30 THEN overdue.open_amount ELSE 0 END) as overdue_1_30,
                SUM(CASE WHEN overdue.age BETWEEN 31 AND 60 THEN overdue.open_amount ELSE 0 END) as overdue_31_60,
                SUM(CASE WHEN overdue.age BETWEEN 61 AND 90 THEN overdue.open_amount ELSE 0 END) as overdue_61_90,
                SUM(CASE WHEN overdue.age > 90 THEN overdue.open_amount ELSE 0 END) as overdue_90_plus,
                SUM(overdue.open_amount) as total_overdue
            FROM ad_org as org
            JOIN ({$overdueQuery}) as overdue ON org.ad_org_id = overdue.ad_org_id
            WHERE overdue.age > 0
        ";

        $bindings = [];
        if ($locationFilter && $locationFilter !== 'National') {
            $mainQuery .= " AND org.name LIKE ?";
            $bindings[] = $locationFilter;
        }

        $mainQuery .= " GROUP BY org.name ORDER BY total_overdue DESC";

        return [
            'query' => $mainQuery,
            'bindings' => $bindings
        ];
    }

    public static function formatNationalRevenueData($queryResult)
    {
        // Sort data according to branch order
        $sortedData = self::sortByBranchOrder(collect($queryResult), 'branch_name');

        $totalRevenue = $sortedData->sum('total_revenue');
        $labels = $sortedData->pluck('branch_name')->map(fn($name) => self::getBranchAbbreviation($name));
        $dataValues = $sortedData->pluck('total_revenue')->all();

        $maxRevenue = $sortedData->max('total_revenue') ?? 0;

        $yAxisConfig = self::getYAxisConfig($maxRevenue, null, $dataValues);

        $suggestedMax = self::calculateSuggestedMax($maxRevenue, $yAxisConfig['divisor']);

        return [
            'totalRevenue' => $totalRevenue,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $dataValues,
                ],
            ],
            'yAxisLabel' => $yAxisConfig['label'],
            'yAxisDivisor' => $yAxisConfig['divisor'],
            'yAxisUnit' => $yAxisConfig['unit'],
            'suggestedMax' => $suggestedMax,
        ];
    }

    /**
     * Get the standard branch order
     */
    public static function getBranchOrder()
    {
        return [
            'MPM Tangerang',
            'PWM Bekasi',
            'PWM Jakarta',
            'PWM Pontianak',
            'PWM Lampung',
            'PWM Banjarmasin',
            'PWM Cirebon',
            'PWM Bandung',
            'PWM Makassar',
            'PWM Surabaya',
            'PWM Semarang',
            'PWM Purwokerto',
            'PWM Denpasar',
            'PWM Palembang',
            'PWM Padang',
            'PWM Medan',
            'PWM Pekanbaru',
        ];
    }

    /**
     * Sort collection by branch order
     */
    public static function sortByBranchOrder($collection, $branchFieldName = 'branch_name')
    {
        $branchOrder = self::getBranchOrder();

        // Create a mapping of branch name to order index
        $orderMap = array_flip($branchOrder);

        // Ensure we are working with a Collection instance
        $collection = $collection instanceof \Illuminate\Support\Collection
            ? $collection
            : collect($collection);

        return $collection->sort(function ($a, $b) use ($orderMap, $branchFieldName) {
            // Support three shapes:
            // 1) object with property $branchFieldName
            // 2) array with key $branchFieldName
            // 3) scalar string when $branchFieldName is null

            if ($branchFieldName === null) {
                $branchA = is_string($a) ? $a : (string) $a;
                $branchB = is_string($b) ? $b : (string) $b;
            } else {
                if (is_object($a)) {
                    $branchA = $a->{$branchFieldName} ?? null;
                } elseif (is_array($a)) {
                    $branchA = $a[$branchFieldName] ?? null;
                } else {
                    $branchA = null;
                }

                if (is_object($b)) {
                    $branchB = $b->{$branchFieldName} ?? null;
                } elseif (is_array($b)) {
                    $branchB = $b[$branchFieldName] ?? null;
                } else {
                    $branchB = null;
                }
            }

            $orderA = $orderMap[$branchA] ?? 999;
            $orderB = $orderMap[$branchB] ?? 999;

            return $orderA <=> $orderB;
        })->values();
    }

    public static function getLocations()
    {
        try {
            $branchOrder = self::getBranchOrder();

            // Get all active locations from database
            $rawLocations = DB::table('ad_org')
                ->where('isactive', 'Y')
                ->whereNotIn('name', ['*', 'HQ', 'Store', 'PWM Pusat'])
                ->pluck('name')
                ->toArray();

            // Sort locations based on the defined order
            $sortedLocations = [];
            foreach ($branchOrder as $branch) {
                if (in_array($branch, $rawLocations)) {
                    $sortedLocations[] = $branch;
                }
            }

            // Add any locations not in the predefined order at the end
            foreach ($rawLocations as $location) {
                if (!in_array($location, $sortedLocations)) {
                    $sortedLocations[] = $location;
                }
            }

            return collect($sortedLocations);
        } catch (\Exception $e) {
            throw new \Exception('Error fetching locations: ' . $e->getMessage());
        }
    }

    public static function getYearlyComparisonDatasets($year, $previousYear, $currentYearValues, $previousYearValues)
    {
        return [
            [
                'label' => $previousYear,
                'data' => $previousYearValues,
                // Blue 500 (lighter) for previous year
                'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                'borderColor' => 'rgba(59, 130, 246, 1)',
                'borderWidth' => 1,
                'borderRadius' => 6,
            ],
            [
                'label' => $year,
                'data' => $currentYearValues,
                // Blue 600 (darker) for current year
                'backgroundColor' => 'rgba(38, 102, 241, 0.9)',
                'borderColor' => 'rgba(37, 99, 235, 1)',
                'borderWidth' => 1,
                'borderRadius' => 6,
            ]
        ];
    }

    public static function getBranchDisplayName(string $branchName): string
    {
        $displayNames = [
            'MPM Tangerang' => 'Tangerang',
            'PWM Bekasi' => 'Bekasi',
            'PWM Jakarta' => 'Jakarta',
            'PWM Pontianak' => 'Pontianak',
            'PWM Lampung' => 'Lampung',
            'PWM Banjarmasin' => 'Banjarmasin',
            'PWM Cirebon' => 'Cirebon',
            'PWM Bandung' => 'Bandung',
            'PWM Makassar' => 'Makassar',
            'PWM Surabaya' => 'Surabaya',
            'PWM Semarang' => 'Semarang',
            'PWM Purwokerto' => 'Purwokerto',
            'PWM Denpasar' => 'Denpasar',
            'PWM Palembang' => 'Palembang',
            'PWM Padang' => 'Padang',
            'PWM Medan' => 'Medan',
            'PWM Pekanbaru' => 'Pekanbaru',
        ];

        return $displayNames[$branchName] ?? $branchName;
    }

    /**
     * Map branch name to database connection
     *
     * @param string $branchName Full branch name (e.g., 'PWM Bandung', 'MPM Tangerang')
     * @return string|null Database connection name (e.g., 'pgsql_bdg', 'pgsql_trg') or null if not found
     */
    public static function getBranchConnection(string $branchName): ?string
    {
        $branchToConnection = [
            'MPM Tangerang' => 'pgsql_trg',
            'PWM Bekasi' => 'pgsql_bks',
            'PWM Jakarta' => 'pgsql_jkt',
            'PWM Pontianak' => 'pgsql_ptk',
            'PWM Lampung' => 'pgsql_lmp',
            'PWM Banjarmasin' => 'pgsql_bjm',
            'PWM Cirebon' => 'pgsql_crb',
            'PWM Bandung' => 'pgsql_bdg',
            'PWM Makassar' => 'pgsql_mks',
            'PWM Surabaya' => 'pgsql_sby',
            'PWM Semarang' => 'pgsql_smg',
            'PWM Purwokerto' => 'pgsql_pwt',
            'PWM Denpasar' => 'pgsql_dps',
            'PWM Palembang' => 'pgsql_plb',
            'PWM Padang' => 'pgsql_pdg',
            'PWM Medan' => 'pgsql_mdn',
            'PWM Pekanbaru' => 'pgsql_pku',
        ];

        return $branchToConnection[$branchName] ?? null;
    }

    /**
     * Calculate percentage growth between two values with decimal precision
     *
     * @param float $currentValue Current period value
     * @param float $previousValue Previous period value
     * @param int $decimalPlaces Number of decimal places (default: 1)
     * @return string|null Formatted percentage string or null if calculation not possible
     */
    public static function calculatePercentageGrowth(float $currentValue, float $previousValue, int $decimalPlaces = 1): ?string
    {
        if ($currentValue <= 0 || $previousValue <= 0) {
            return null;
        }

        $growth = (($currentValue - $previousValue) / $previousValue) * 100;
        $prefix = $growth >= 0 ? '' : '';

        return $prefix . number_format($growth, $decimalPlaces, '.', '') . '%';
    }

    /**
     * Format value with appropriate unit (M/B) and decimal precision
     *
     * @param float $value Raw value
     * @param float $divisor Divisor (1e6 for millions, 1e9 for billions)
     * @param string $unit Unit string ('M' or 'B')
     * @param int $decimalPlaces Number of decimal places
     * @return string|null Formatted value string or null for zero values
     */
    public static function formatValueWithUnit(float $value, float $divisor, string $unit, int $decimalPlaces = 1): ?string
    {
        if ($value === 0) {
            return null;
        }

        $scaledValue = $value / $divisor;

        if ($unit === 'M') {
            $rounded = round($scaledValue, $decimalPlaces);
            $display = ($rounded % 1 === 0) ? number_format($rounded, 0) : number_format($rounded, $decimalPlaces, '.', '');
            return $display . 'M';
        }

        return number_format(round($scaledValue), 0) . 'Jt';
    }

    /**
     * Get available product categories
     */
    public static function getCategories()
    {
        return DB::table('m_product_category')
            ->where('isactive', 'Y')
            ->whereIn('name', ['MIKA', 'SPARE PART'])
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate fair comparison date ranges for year-over-year analysis
     *
     * This method ensures both years have the same period length for accurate comparison.
     * For example, if today is September 24, 2025, instead of comparing:
     * - 2024: Jan 1 - Dec 31 (full year)
     * - 2025: Jan 1 - Sep 24 (partial year)
     *
     * It will compare:
     * - 2024: Jan 1 - Sep 24 (same period)
     * - 2025: Jan 1 - Sep 24 (same period)
     *
     * This eliminates unfair percentage comparisons caused by different period lengths.
     *
     * @param string $currentEndDate Current period end date (Y-m-d format)
     * @param int $previousYear Previous year to compare against
     * @return array Array with 'current' and 'previous' date range arrays
     */
    public static function calculateFairComparisonDateRanges(string $currentEndDate, int $previousYear): array
    {
        $currentYear = date('Y', strtotime($currentEndDate));

        // Current year range: Jan 1 to specified end date
        $currentStartDate = $currentYear . '-01-01';

        // Previous year range: Jan 1 to same month/day as current end date
        $endDateParts = explode('-', $currentEndDate);
        $currentMonth = $endDateParts[1];
        $currentDay = $endDateParts[2];

        $previousStartDate = $previousYear . '-01-01';
        $previousEndDate = $previousYear . '-' . $currentMonth . '-' . $currentDay;

        // Validate the previous end date exists (handle Feb 29 on non-leap years)
        if (!checkdate($currentMonth, $currentDay, $previousYear)) {
            // If date doesn't exist (like Feb 29 on non-leap year), use last day of that month
            $previousEndDate = date('Y-m-t', strtotime($previousYear . '-' . $currentMonth . '-01'));
        }

        return [
            'current' => [
                'start' => $currentStartDate,
                'end' => $currentEndDate
            ],
            'previous' => [
                'start' => $previousStartDate,
                'end' => $previousEndDate
            ]
        ];
    }
}

<?php

namespace App\Helpers;

use Carbon\Carbon;

class NumberFormattingHelper
{
    const BILLION_THRESHOLD = 1000000000;
    const MILLION_THRESHOLD = 1000000;

    /**
     * Get the appropriate Y-axis label (Million or Billion Rupiah).
     *
     * @param float $maxValue The maximum value to determine the scale.
     * @param float $billionThreshold The threshold to switch to billions.
     * @return string
     */
    public static function getYAxisLabel(float $maxValue, float $billionThreshold = self::BILLION_THRESHOLD): string
    {
        return ($maxValue >= $billionThreshold) ? 'Billion Rupiah (Rp)' : 'Million Rupiah (Rp)';
    }

    /**
     * Get the divisor for Y-axis scaling (1 million or 1 billion).
     *
     * @param float $maxValue The maximum value to determine the scale.
     * @param float $billionThreshold The threshold to switch to billions.
     * @return int
     */
    public static function getAxisDivisor(float $maxValue, float $billionThreshold = self::BILLION_THRESHOLD): int
    {
        return ($maxValue >= $billionThreshold) ? self::BILLION_THRESHOLD : self::MILLION_THRESHOLD;
    }

    /**
     * Format a number as Rupiah for display.
     *
     * @param float $value
     * @param int $decimals
     * @return string
     */
    public static function formatRupiah(float $value, int $decimals = 0): string
    {
        return 'Rp ' . number_format($value, $decimals, ',', '.');
    }

    /**
     * Format a number for chart axis tick display (value / divisor).
     *
     * @param float $value
     * @param int $divisor
     * @param int $precision
     * @return float
     */
    public static function formatForChartAxis(float $value, int $divisor, int $precision = 0): float
    {
        if ($divisor === 0) return 0;
        return round($value / $divisor, $precision);
    }

    /**
     * Format a number for chart tooltip display (full number).
     *
     * @param float $value
     * @param int $decimals
     * @return string
     */
    public static function formatForTooltip(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    /**
     * Get the short branch name.
     *
     * @param string $fullBranchName
     * @return string
     */
    public static function getShortBranchName(string $fullBranchName): string
    {
        $shortcodes = [
            'PWM Surabaya' => 'SBY',
            'PWM Jakarta' => 'JKT',
            'PWM Bandung' => 'BDG',
        ];
        return $shortcodes[$fullBranchName] ?? strtoupper(substr(str_replace('PWM ', '', $fullBranchName), 0, 3));
    }

    /**
     * Calculate suggested max for a chart axis based on max data value and desired scale.
     *
     * @param float $maxDataValue The highest data point in the series.
     * @param int $divisor The divisor being used (e.g., 1M or 1B).
     * @param float $paddingFactor Percentage padding (e.g., 1.05 for 5% padding).
     * @param int $defaultScaledMax Default max if no data (e.g., 100 for 100M or 100B).
     * @return float The raw suggested max value (not divided).
     */
    public static function calculateSuggestedMax(float $maxDataValue, int $divisor, float $paddingFactor = 1.1, int $defaultScaledUnits = 100): float
    {
        if ($maxDataValue <= 0) {
            return $defaultScaledUnits * $divisor; // e.g., 100 Million or 100 Billion
        }

        // Calculate a padded max value
        $paddedMax = $maxDataValue * $paddingFactor;

        // Round up to the nearest nice number in the current scale
        // For example, if paddedMax is 73M and divisor is 1M, round to 80M.
        // If paddedMax is 7.3B and divisor is 1B, round to 8B.
        $scaledPaddedMax = $paddedMax / $divisor;
        $roundedScaledMax = ceil($scaledPaddedMax / 10) * 10; // Round up to nearest 10 units of scale

        if ($roundedScaledMax < $scaledPaddedMax) { // Ensure it's truly an upper bound
            $roundedScaledMax = (floor($scaledPaddedMax / 10) + 1) * 10;
        }
        if ($roundedScaledMax == 0 && $scaledPaddedMax > 0) { // Handle very small values that round to 0
            $roundedScaledMax = 10; // Smallest sensible step
        }

        // If the maxDataValue is very small compared to the default (e.g. 1M vs 100M default)
        // we might want a smaller axis. Let's cap at defaultScaledUnits * divisor if it's smaller.
        $finalSuggestedMax = $roundedScaledMax * $divisor;

        // Ensure the suggested max is at least slightly larger than maxDataValue if data exists
        if ($maxDataValue > 0 && $finalSuggestedMax <= $maxDataValue) {
            $finalSuggestedMax = (ceil(($maxDataValue / $divisor) / 10) + 1) * 10 * $divisor;
            if ($finalSuggestedMax <= $maxDataValue) { // Final check for edge cases
                $finalSuggestedMax = (floor(($maxDataValue / $divisor) / 10) + 2) * 10 * $divisor; // Add more aggressive step up
            }
        }

        // If data is very small, but not zero, ensure axis isn't excessively large
        // e.g. if max data is 2M, axis shouldn't be 100M. It should be like 5M or 10M.
        if ($maxDataValue > 0 && $maxDataValue < $defaultScaledUnits * $divisor / 10) { // if data is less than 1/10th of default max scale
            $finalSuggestedMax = (ceil(($maxDataValue / $divisor) / ($divisor == self::BILLION_THRESHOLD ? 1 : 5)) + 1) * ($divisor == self::BILLION_THRESHOLD ? 1 : 5) * $divisor;
        }

        // Fallback to a simple padding if complex rounding is problematic or too large
        if ($finalSuggestedMax > $maxDataValue * 2 && $maxDataValue > 0) { // if it's more than double, it might be too much
            $finalSuggestedMax = ceil($maxDataValue * $paddingFactor / ($divisor / 10)) * ($divisor / 10); // round to 1/10th of divisor
            if ($finalSuggestedMax <= $maxDataValue) $finalSuggestedMax = ceil($maxDataValue * 1.2); // simple 20% padding raw
        }

        return $finalSuggestedMax > 0 ? $finalSuggestedMax : $defaultScaledUnits * $divisor;
    }
}

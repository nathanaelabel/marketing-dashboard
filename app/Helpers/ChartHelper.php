<?php

namespace App\Helpers;

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
        $billionThreshold = 1e9;    // 1 Billion
        $forceVeryHighBillionsThreshold = 2 * 1e9; // e.g., if max is 2B or more, definitely use Billions
        $forceMillionLowThreshold = 2 * 1e6; // Values below this (e.g., 2M) will definitely use Millions scale
        $significantSmallerValueThreshold = 0.7 * 1e9; // e.g., 700M. If a bar is below this, it's a 'smaller' significant value.

        if ($maxValue < $forceMillionLowThreshold) {
            return ['label' => 'Million Rupiah (Rp)', 'divisor' => 1e6, 'unit' => 'M'];
        }

        // If maxValue is very high, force Billions
        if ($maxValue >= $forceVeryHighBillionsThreshold) {
            return ['label' => 'Billion Rupiah (Rp)', 'divisor' => 1e9, 'unit' => 'B'];
        }

        // If maxValue is between 1B (inclusive) and forceVeryHighBillionsThreshold (exclusive)
        if ($maxValue >= $billionThreshold) {
            $preferMillions = false;
            if (!empty($allDataValues)) {
                $nonZeroValues = array_filter($allDataValues, fn($v) => $v > 0.001 * $billionThreshold); // Consider values > 1M as somewhat relevant
                if (count($nonZeroValues) > 1) { // Only apply this mixed-scale logic if there's more than one bar
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
                return ['label' => 'Million Rupiah (Rp)', 'divisor' => 1e6, 'unit' => 'M'];
            }
            // Otherwise (maxValue is 1B up to $forceVeryHighBillionsThreshold and no significantly smaller bars, or only one bar), use Billions
            return ['label' => 'Billion Rupiah (Rp)', 'divisor' => 1e9, 'unit' => 'B'];
        }

        // Default: maxValue is < 1B (and >= $forceMillionLowThreshold)
        return ['label' => 'Million Rupiah (Rp)', 'divisor' => 1e6, 'unit' => 'M'];
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
            return round($value / 1e9, $precision) . 'B';
        }
        if ($value >= 1e6) {
            return round($value / 1e6, $precision) . 'M';
        }
        if ($value >= 1e3) {
            return round($value / 1e3, $precision) . 'K';
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
        float $paddingFactor = 1.0, // From 1.2 to 1.15 for tighter Y-axis (adjust)
        float $minSuggestedMaxDivisorUnits = 5.0 // e.g. default to 5M or 5B if data is tiny/zero
    ): float {
        if ($maxDataValue <= 0) {
            // If no data or zero/negative max, return a minimum sensible default.
            // e.g., if divisor is 1e6 (Millions), return 5 * 1e6 = 5 Million.
            return $minSuggestedMaxDivisorUnits * $divisor;
        }

        // Calculate suggested max by adding padding to the actual max data value.
        $suggestedMax = $maxDataValue * $paddingFactor;

        // Ensure the suggestedMax is at least the minimum defined by minSuggestedMaxDivisorUnits.
        // This prevents the axis from being too small if maxDataValue is positive but very tiny.
        $minimumSensibleMax = $minSuggestedMaxDivisorUnits * $divisor;
        if ($suggestedMax < $minimumSensibleMax && $maxDataValue < $minimumSensibleMax) {
            // Only apply this if maxDataValue itself is also below the minimum sensible max
            // to avoid inflating a small dataset (e.g. max 1M) to 5M if 5M is the minimum.
            // The goal is to have a reasonable *minimum* axis height, not to always force it to 5M if data is 1M.
            // Let's refine: if maxDataValue is 1M, suggestedMax (1.2M) might be less than minimumSensibleMax (5M).
            // In this case, we want the axis to go up to a bit above 1.2M, not jump to 5M.
            // The $minSuggestedMaxDivisorUnits is more for the $maxDataValue <= 0 case.
            // For positive data, we just want to ensure it's not ridiculously small.
            // A better approach for positive data: ensure the suggested max is at least slightly larger than maxDataValue.
            // And make sure it's a 'round-ish' number in terms of the divisor.
        }

        // Let's simplify: just pad the max value. The Chart.js `suggestedMax` will already try to find nice ticks.
        // If the padded value is very small, ensure it's at least one unit of the divisor for visibility.
        if ($suggestedMax < $divisor) {
            return $divisor; // At least 1M or 1B
        }

        return $suggestedMax;
    }
}

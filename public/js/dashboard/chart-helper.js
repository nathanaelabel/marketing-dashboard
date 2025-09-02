const ChartHelper = {
    calculateYAxisMax(datasets, increment) {
        let maxTotal = 0;
        if (datasets.length > 0) {
            const dataLength = datasets[0].data.length;
            for (let i = 0; i < dataLength; i++) {
                let total = 0;
                for (const dataset of datasets) {
                    total += dataset.data[i] || 0;
                }
                if (total > maxTotal) {
                    maxTotal = total;
                }
            }
        }
        return Math.ceil(maxTotal / increment) * increment;
    },

    formatCurrency(value, divisor) {
        const scaledValue = value / divisor;
        if (scaledValue >= 1) {
            return scaledValue.toFixed(0);
        } else if (scaledValue > 0) {
            return scaledValue.toFixed(1);
        } else {
            return '0';
        }
    },

    formatYAxisLabel(value) {
        if (value >= 1000000) {
            return (value / 1000000) + 'M';
        } else if (value >= 1000) {
            return (value / 1000) + 'K';
        }
        return value;
    },

    showLoadingIndicator(chartContainer) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'absolute inset-0 bg-white bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-10 loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="text-center">
                <p class="text-lg font-semibold text-gray-700">Loading chart data...</p>
            </div>
        `;
        chartContainer.style.position = 'relative';
        chartContainer.appendChild(loadingOverlay);
    },

    hideLoadingIndicator(chartContainer) {
        const loadingOverlay = chartContainer.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.remove();
        }
    },

    /**
     * Calculate percentage growth between two values with decimal precision
     * @param {number} currentValue - Current period value
     * @param {number} previousValue - Previous period value
     * @param {number} decimalPlaces - Number of decimal places (default: 1)
     * @returns {string|null} Formatted percentage string or null if calculation not possible
     */
    calculatePercentageGrowth(currentValue, previousValue, decimalPlaces = 1) {
        if (currentValue <= 0 || previousValue <= 0) {
            return null;
        }
        
        const growth = ((currentValue - previousValue) / previousValue) * 100;
        const prefix = growth >= 0 ? '' : '';
        
        return prefix + growth.toFixed(decimalPlaces) + '%';
    },

    /**
     * Format value with appropriate unit (M/B) and decimal precision
     * @param {number} value - Raw value
     * @param {number} divisor - Divisor (1e6 for millions, 1e9 for billions)
     * @param {string} unit - Unit string ('M' or 'B')
     * @param {number} decimalPlaces - Number of decimal places
     * @returns {string|null} Formatted value string or null for zero values
     */
    formatValueWithUnit(value, divisor, unit, decimalPlaces = 1) {
        if (value === 0) {
            return null;
        }
        
        const scaledValue = value / divisor;
        
        if (unit === 'B') {
            const rounded = Math.round(scaledValue * Math.pow(10, decimalPlaces)) / Math.pow(10, decimalPlaces);
            const display = (rounded % 1 === 0) ? rounded.toFixed(0) : rounded.toFixed(decimalPlaces);
            return display + 'B';
        }
        
        return Math.round(scaledValue).toFixed(0) + 'M';
    },

    /**
     * Create a reusable growth label formatter for Chart.js datalabels
     * @param {number} decimalPlaces - Number of decimal places for percentage (default: 1)
     * @returns {function} Formatter function for Chart.js datalabels
     */
    createGrowthLabelFormatter(decimalPlaces = 1) {
        return (value, context) => {
            const datasets = context.chart.data.datasets;
            if (datasets.length === 2) {
                const currentValue = datasets[1].data[context.dataIndex];
                const previousValue = datasets[0].data[context.dataIndex];

                // Only show growth on the higher bar
                const isHigherBar = (context.datasetIndex === 1 && currentValue >= previousValue) ||
                    (context.datasetIndex === 0 && previousValue > currentValue);

                if (isHigherBar && currentValue > 0 && previousValue > 0) {
                    return this.calculatePercentageGrowth(currentValue, previousValue, decimalPlaces);
                }
            }
            return null;
        };
    }
};

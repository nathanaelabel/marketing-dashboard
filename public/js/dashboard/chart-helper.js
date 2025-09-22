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
            return (value / 1000000) + 'Jt';
        } else if (value >= 1000) {
            return (value / 1000) + 'rb';
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

    showChartLoadingIndicator(chartElement) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 chart-loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                <p class="text-sm font-medium text-gray-700">Loading chart data...</p>
            </div>
        `;
        chartElement.style.position = 'relative';
        chartElement.appendChild(loadingOverlay);
    },

    hideChartLoadingIndicator(chartElement) {
        const loadingOverlay = chartElement.querySelector('.chart-loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.remove();
        }
    },

    disableFilters(filterSelectors) {
        filterSelectors.forEach(selector => {
            const element = document.querySelector(selector) || document.getElementById(selector);
            if (element) {
                element.disabled = true;
                element.style.opacity = '0.6';
                element.style.cursor = 'not-allowed';
            }
        });
    },

    enableFilters(filterSelectors) {
        filterSelectors.forEach(selector => {
            const element = document.querySelector(selector) || document.getElementById(selector);
            if (element) {
                element.disabled = false;
                element.style.opacity = '1';
                element.style.cursor = 'pointer';
            }
        });
    },

    hideLoadingIndicator(chartContainer) {
        const loadingOverlay = chartContainer.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.remove();
        }
    },

    showErrorMessage(chartInstance, chartContext, message) {
        if (chartInstance) {
            chartInstance.data.labels = [];
            chartInstance.data.datasets = [];
            chartInstance.update();
        }

        const chartContainer = chartContext.parentElement;

        // Clear previous messages
        const existingError = chartContainer.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-center p-4 text-red-500 flex items-center justify-center';
        errorDiv.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>${message}</span>`;
        chartContainer.appendChild(errorDiv);
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

        if (unit === 'M') {
            const rounded = Math.round(scaledValue * Math.pow(10, decimalPlaces)) / Math.pow(10, decimalPlaces);
            const display = (rounded % 1 === 0) ? rounded.toFixed(0) : rounded.toFixed(decimalPlaces);
            return display + 'M';
        }

        return Math.round(scaledValue).toFixed(0) + 'Jt';
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
    },

    /**
     * Format number for display with appropriate scaling
     * @param {number} value - Raw value to format
     * @param {number} divisor - Divisor for scaling (1, 1000000, 1000000000)
     * @returns {string} Formatted display string
     */
    formatNumberForDisplay(value, divisor) {
        if (value === 0) return '0';

        const scaledValue = value / divisor;

        if (divisor === 1000000000) { // Billions
            if (scaledValue % 1 === 0) return scaledValue.toFixed(0) + 'M';
            return scaledValue.toFixed(1) + 'M';
        } else if (divisor === 1000000) { // Millions
            return Math.round(scaledValue) + 'Jt';
        } else {
            // For raw values or thousands
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(scaledValue);
        }
    },

    /**
     * Show no data message in chart container
     * @param {object} chartInstance - Chart.js instance
     * @param {HTMLElement} chartContext - Canvas element
     * @param {string} message - Message to display
     */
    showNoDataMessage(chartInstance, chartContext, message) {
        if (chartInstance) {
            chartInstance.data.labels = [];
            chartInstance.data.datasets = [];
            chartInstance.update();
        }

        const chartContainer = chartContext.parentElement;

        // Clear previous messages
        const existingMessage = chartContainer.querySelector('.no-data-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'no-data-message text-center p-4 text-gray-600';
        messageDiv.innerHTML = `<i class="fas fa-info-circle mr-2"></i>${message}`;
        chartContainer.appendChild(messageDiv);
    },

    /**
     * Centralized currency formatting function with Indonesian units
     * @param {number} value - Raw value to format
     * @param {number} precision - Decimal precision (default: 1)
     * @param {boolean} includeRp - Whether to include 'Rp' prefix (default: true)
     * @returns {string} Formatted currency string
     */
    formatCurrencyDisplay(value, precision = 1, includeRp = true) {
        const prefix = includeRp ? 'Rp ' : '';

        if (Math.abs(value) >= 1e9) {
            return `${prefix}${(value / 1e9).toFixed(precision)}M`;
        } else if (Math.abs(value) >= 1e6) {
            return `${prefix}${(value / 1e6).toFixed(precision)}Jt`;
        } else if (Math.abs(value) >= 1e3) {
            return `${prefix}${(value / 1e3).toFixed(precision)}rb`;
        }
        return `${prefix}${value}`;
    }
};

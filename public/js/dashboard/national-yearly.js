document.addEventListener('DOMContentLoaded', function () {
    let nationalYearlyChart = null;
    const ctx = document.getElementById('national-yearly-chart');
    const yearSelect = document.getElementById('yearly-year-select');
    const categorySelect = document.getElementById('yearly-category-select');
    const typeSelect = document.getElementById('yearly-type-select');
    const previousYearLabel = document.getElementById('previous-year-label');
    const currentYearLabel = document.getElementById('current-year-label');

    if (!ctx) return;

    function updateNationalYearlyChart(dataFromServer) {
        if (!dataFromServer) {
            console.error('No data received from server');
            return;
        }

        const chartLabels = dataFromServer.labels;
        const yAxisLabel = dataFromServer.yAxisLabel;
        const yAxisDivisor = dataFromServer.yAxisDivisor;
        const suggestedMax = dataFromServer.suggestedMax;

        // Use centralized formatting from ChartHelper
        const formatValueWithUnit = (value) => {
            return ChartHelper.formatValueWithUnit(value, yAxisDivisor, dataFromServer.yAxisUnit, 1);
        };

        const formatGrowthLabel = (value, context) => {
            const datasets = context.chart.data.datasets;
            if (datasets.length === 2) {
                const currentValue = datasets[1].data[context.dataIndex];
                const previousValue = datasets[0].data[context.dataIndex];

                // Only show growth on the higher bar
                const isHigherBar = (context.datasetIndex === 1 && currentValue >= previousValue) ||
                    (context.datasetIndex === 0 && previousValue > currentValue);

                if (isHigherBar && currentValue > 0 && previousValue > 0) {
                    const growth = ((currentValue - previousValue) / previousValue) * 100;
                    return (growth >= 0 ? 'Rp ' : '') + growth.toFixed(1) + '%';
                }
            }
            return null;
        };

        if (nationalYearlyChart) {
            nationalYearlyChart.data.labels = chartLabels;
            nationalYearlyChart.data.datasets = dataFromServer.datasets;
            nationalYearlyChart.options.scales.y.title.text = yAxisLabel;
            nationalYearlyChart.options.scales.y.suggestedMax = suggestedMax;
            nationalYearlyChart.options.scales.y.ticks.callback = function (value) {
                const scaledValue = value / yAxisDivisor;
                if (dataFromServer.yAxisUnit === 'B') {
                    if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                    return scaledValue.toFixed(1);
                } else {
                    return Math.round(scaledValue);
                }
            };
            nationalYearlyChart.options.plugins.datalabels.formatter = formatGrowthLabel;
            nationalYearlyChart.update();
        } else {
            // Register datalabels + custom legend margin plugin
            const LegendMargin = {
                id: 'legendMargin',
                beforeInit(chart, _args, opts) {
                    const fit = chart.legend && chart.legend.fit;
                    if (!fit) return;
                    chart.legend.fit = function fitWithMargin() {
                        fit.bind(this)();
                        this.height += (opts && opts.margin) ? opts.margin : 0;
                    };
                }
            };

            Chart.register(ChartDataLabels, LegendMargin);
            nationalYearlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: dataFromServer.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        },
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                padding: 12
                            }
                        },
                        // extra spacing below legend (to separate from plot area)
                        legendMargin: {
                            margin: 10
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('id-ID', {
                                            minimumFractionDigits: 0
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            formatter: formatGrowthLabel,
                            font: {
                                weight: 'bold'
                            },
                            color: '#444'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: suggestedMax,
                            title: {
                                display: true,
                                text: yAxisLabel,
                                padding: {
                                    top: 0,
                                    left: 0,
                                    right: 0,
                                    bottom: 20
                                }
                            },
                            ticks: {
                                callback: function (value) {
                                    const scaledValue = value / yAxisDivisor;
                                    if (dataFromServer.yAxisUnit === 'B') {
                                        if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                                        return scaledValue.toFixed(1);
                                    } else {
                                        return Math.round(scaledValue);
                                    }
                                }
                            }
                        },
                        x: {
                            title: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }

    function fetchAndUpdateYearlyChart(year, category, type, retryCount = 0) {
        const url = `/national-yearly/data?year=${year}&category=${encodeURIComponent(category)}&type=${encodeURIComponent(type)}`;
        const filterSelectors = ['yearly-year-select', 'yearly-category-select', 'yearly-type-select'];
        const chartContainer = document.getElementById('national-yearly-chart');
        const maxRetries = 2; // Maximum number of retries
        const retryDelay = 2000; // 2 seconds delay between retries

        // Create a wrapper div around canvas if it doesn't exist
        if (!chartContainer.parentElement.classList.contains('chart-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'chart-wrapper relative w-full';
            wrapper.style.width = '100%';
            wrapper.style.height = 'auto';
            chartContainer.parentElement.insertBefore(wrapper, chartContainer);
            wrapper.appendChild(chartContainer);
        }

        // Disable filters and show loading on chart area only
        ChartHelper.disableFilters(filterSelectors);
        ChartHelper.showChartLoadingIndicator(chartContainer.parentElement);

        // Create AbortController for timeout handling
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 seconds timeout

        fetch(url, {
            signal: controller.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                clearTimeout(timeoutId);

                if (!response.ok) {
                    // Try to get error message from response
                    return response.json().then(errorData => {
                        throw new Error(errorData.message || errorData.error || `HTTP error! status: ${response.status}`);
                    }).catch(() => {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                clearTimeout(timeoutId);

                // Check if response contains error
                if (data.error) {
                    throw new Error(data.message || data.error);
                }

                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);
                updateNationalYearlyChart(data);
            })
            .catch(error => {
                clearTimeout(timeoutId);

                console.error('Error fetching National Yearly data:', error);

                // Retry logic for timeout or network errors
                if ((error.name === 'AbortError' || error.message.includes('timeout') || error.message.includes('Failed to fetch')) && retryCount < maxRetries) {
                    console.log(`Retrying request (attempt ${retryCount + 1}/${maxRetries})...`);
                    setTimeout(() => {
                        fetchAndUpdateYearlyChart(year, category, type, retryCount + 1);
                    }, retryDelay);
                    return;
                }

                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);

                // Show more specific error message
                let errorMessage = 'Connection timed out. Try refreshing the page.';
                if (error.message) {
                    if (error.message.includes('timeout') || error.message.includes('Request timeout')) {
                        errorMessage = 'Request timeout. The server is taking too long to respond. Please wait a moment and try again.';
                    } else if (error.message.includes('Failed to fetch') || error.name === 'TypeError') {
                        errorMessage = 'Network error. Please check your connection and try again.';
                    } else if (error.message.includes('HTTP error! status: 500')) {
                        errorMessage = 'Server error. The query is taking too long. Please try again in a moment.';
                    } else {
                        errorMessage = error.message;
                    }
                }

                ChartHelper.showErrorMessage(nationalYearlyChart, ctx, errorMessage);
            });
    }

    const triggerUpdate = () => {
        const year = yearSelect.value;
        const category = categorySelect.value;
        const type = typeSelect.value;

        // Update year labels (if elements exist; header legend was removed)
        if (previousYearLabel) previousYearLabel.textContent = (year - 1);
        if (currentYearLabel) currentYearLabel.textContent = year;

        // Show comparison period info for current year
        const periodDescription = ChartHelper.getFairComparisonPeriodDescription(year);
        console.log(periodDescription); // For debugging - can be displayed in UI if needed

        fetchAndUpdateYearlyChart(year, category, type);
    };

    // Event listeners
    yearSelect.addEventListener('change', triggerUpdate);
    categorySelect.addEventListener('change', triggerUpdate);
    typeSelect.addEventListener('change', triggerUpdate);

    // Initialize
    triggerUpdate();

    // Three-dots menu toggle
    const menuButton = document.getElementById('nyMenuButton');
    const dropdownMenu = document.getElementById('nyDropdownMenu');

    if (menuButton && dropdownMenu) {
        // Toggle dropdown on button click
        menuButton.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!menuButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });
    }

    // Refresh Data functionality
    const refreshBtn = document.getElementById('nyRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Refresh the chart
            triggerUpdate();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('nyExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentYear = yearSelect.value;
            const currentCategory = categorySelect.value;
            const currentType = typeSelect.value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportUrl = `/national-yearly/export-excel?year=${currentYear}&category=${encodeURIComponent(currentCategory)}&type=${encodeURIComponent(currentType)}`;

            // Use window.location for direct download
            window.location.href = exportUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalContent;
            }, 2000);
        });
    }

    // Export to PDF functionality
    const exportPdfBtn = document.getElementById('nyExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentYear = yearSelect.value;
            const currentCategory = categorySelect.value;
            const currentType = typeSelect.value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportPdfUrl = `/national-yearly/export-pdf?year=${currentYear}&category=${encodeURIComponent(currentCategory)}&type=${encodeURIComponent(currentType)}`;

            // Use window.location for direct download
            window.location.href = exportPdfUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                exportPdfBtn.disabled = false;
                exportPdfBtn.innerHTML = originalContent;
            }, 2000);
        });
    }
});

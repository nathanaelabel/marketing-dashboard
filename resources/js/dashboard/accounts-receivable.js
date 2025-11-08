document.addEventListener('DOMContentLoaded', function () {
    const arChartCanvas = document.getElementById('accountsReceivableChart');
    let arChart;

    if (!arChartCanvas) return;

    // Initialize Flatpickr for Current Date
    const arCurrentDatePicker = flatpickr('#ar_current_date', {
        dateFormat: 'Y-m-d',
        altFormat: 'd-m-Y',
        altInput: true,
        defaultDate: document.getElementById('ar_current_date').value || new Date(),
        maxDate: 'today',
        onChange: function (selectedDates, dateStr, instance) {
            // Refresh chart when date changes
            fetchAndUpdateAccountsReceivableChart();
            
            // Dispatch custom event to notify sales metrics to refresh pie chart
            document.dispatchEvent(new CustomEvent('ar-date-changed', {
                detail: { date: dateStr }
            }));
        }
    });

    // Custom plugin to add extra margin under the legend
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

    let currentFilter = 'overdue'; // Default filter

    function fetchAndUpdateAccountsReceivableChart(filter = null) {
        if (filter) {
            currentFilter = filter;
        }

        // Get current date from date picker
        const currentDate = document.getElementById('ar_current_date').value;

        const arTotalEl = document.getElementById('arTotal');
        const arDateEl = document.getElementById('arDate');
        const chartContainer = document.getElementById('ar-chart-container');
        const refreshBtn = document.getElementById('arRefreshDataBtn');
        const currentDateInput = document.getElementById('ar_current_date');
        const filterSelect = document.getElementById('arFilterSelect');

        // Show loading state
        arTotalEl.textContent = 'Loading chart data...';
        if (chartContainer) {
            ChartHelper.showChartLoadingIndicator(chartContainer);
        }
        if (refreshBtn) {
            refreshBtn.disabled = true;
        }
        // Disable filters during loading
        if (currentDateInput) {
            currentDateInput.disabled = true;
            // Also disable the flatpickr alt input
            const altInput = currentDateInput.nextElementSibling;
            if (altInput && altInput.classList.contains('flatpickr-input')) {
                altInput.disabled = true;
            }
        }
        if (filterSelect) {
            filterSelect.disabled = true;
        }

        const baseUrl = arChartCanvas.dataset.url;
        const url = `${baseUrl}?filter=${currentFilter}&current_date=${currentDate}`;
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (arChart) {
                    arChart.destroy();
                }

                // Hide loading indicator
                if (chartContainer) {
                    ChartHelper.hideChartLoadingIndicator(chartContainer);
                }
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                }
                // Re-enable filters after loading
                if (currentDateInput) {
                    currentDateInput.disabled = false;
                    const altInput = currentDateInput.nextElementSibling;
                    if (altInput && altInput.classList.contains('flatpickr-input')) {
                        altInput.disabled = false;
                    }
                }
                if (filterSelect) {
                    filterSelect.disabled = false;
                }

                arTotalEl.textContent = data.total;

                // Determine scale based on filter and max value (excluding OFFLINE dataset)
                let maxValue = 0;
                data.datasets.forEach(dataset => {
                    if (dataset.label !== 'OFFLINE') {
                        dataset.data.forEach(value => {
                            maxValue = Math.max(maxValue, value || 0);
                        });
                    }
                });

                // Calculate total stacked values per bar (excluding OFFLINE dataset)
                let maxStackedValue = 0;
                if (data.datasets.length > 0 && data.datasets[0].data.length > 0) {
                    for (let i = 0; i < data.datasets[0].data.length; i++) {
                        let stackTotal = 0;
                        data.datasets.forEach(dataset => {
                            if (dataset.label !== 'OFFLINE') {
                                stackTotal += dataset.data[i] || 0;
                            }
                        });
                        maxStackedValue = Math.max(maxStackedValue, stackTotal);
                    }
                }

                // Determine scale configuration based on filter
                const isOverdueFilter = currentFilter === 'overdue';
                const useMillions = isOverdueFilter; // Overdue uses Millions, All uses Billions
                const divisor = useMillions ? 1000000 : 1000000000;
                const unit = useMillions ? 'Jt' : 'M';
                const yAxisLabel = useMillions ? 'Juta Rupiah' : 'Miliar Rupiah';
                const labelThreshold = useMillions ? 40000000 : 1000000000; // 40M for Overdue, 1B for All

                // Calculate yMax with appropriate increment
                const increment = useMillions ? 100000000 : 2000000000; // 100M for Overdue, 2B for All
                const yMax = Math.ceil(maxStackedValue / increment) * increment;

                arChart = new Chart(arChartCanvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    padding: 12,
                                    filter: function(legendItem, chartData) {
                                        // Hide OFFLINE from legend
                                        return legendItem.text !== 'OFFLINE';
                                    }
                                }
                            },
                            // extra space under legend
                            legendMargin: {
                                margin: 10
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function (context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            // Handle negative zero and format with Indonesian number format
                                            const value = Math.abs(context.parsed.y) < 0.01 ? 0 : context.parsed.y;
                                            const formattedValue = Math.round(value).toLocaleString('id-ID');
                                            label += formattedValue;
                                        }
                                        return label;
                                    },
                                    footer: function (tooltipItems) {
                                        // Calculate total from all datasets for this branch
                                        let total = 0;
                                        tooltipItems.forEach(function (tooltipItem) {
                                            total += tooltipItem.parsed.y;
                                        });
                                        // Handle negative zero
                                        total = Math.abs(total) < 0.01 ? 0 : total;
                                        return 'Total: ' + Math.round(total).toLocaleString('id-ID');
                                    }
                                }
                            },
                            datalabels: {
                                display: function(context) {
                                    // For OFFLINE dataset, use its own config
                                    if (context.dataset.label === 'OFFLINE') {
                                        return context.dataset.datalabels?.display ?? false;
                                    }
                                    return true;
                                },
                                color: function(context) {
                                    if (context.dataset.label === 'OFFLINE') {
                                        return context.dataset.datalabels?.color ?? '#333';
                                    }
                                    return '#333';
                                },
                                font: function(context) {
                                    if (context.dataset.label === 'OFFLINE') {
                                        return context.dataset.datalabels?.font ?? { weight: 'bold', size: 11 };
                                    }
                                    return { weight: 'bold', size: 11 };
                                },
                                rotation: function(context) {
                                    if (context.dataset.label === 'OFFLINE') {
                                        return context.dataset.datalabels?.rotation ?? 0;
                                    }
                                    return 0;
                                },
                                formatter: function (value, context) {
                                    // For OFFLINE bars, use dataset-specific formatter
                                    if (context.dataset.label === 'OFFLINE') {
                                        return value !== null && value > 0 ? 'OFFLINE' : null;
                                    }
                                    // For other datasets, show value if above threshold
                                    if (value < labelThreshold) {
                                        return null;
                                    }
                                    const scaledValue = value / divisor;
                                    const display = scaledValue.toFixed(1);
                                    return display + unit;
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                max: yMax,
                                ticks: {
                                    callback: function (value) {
                                        const scaledValue = value / divisor;
                                        return Math.round(scaledValue);
                                    }
                                },
                                title: {
                                    display: true,
                                    text: yAxisLabel
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching Accounts Receivable data:', error);
                if (arTotalEl) arTotalEl.textContent = 'Error loading data.';
                if (chartContainer) {
                    ChartHelper.hideChartLoadingIndicator(chartContainer);
                }
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                }
                // Re-enable filters on error
                if (currentDateInput) {
                    currentDateInput.disabled = false;
                    const altInput = currentDateInput.nextElementSibling;
                    if (altInput && altInput.classList.contains('flatpickr-input')) {
                        altInput.disabled = false;
                    }
                }
                if (filterSelect) {
                    filterSelect.disabled = false;
                }
                ChartHelper.showErrorMessage(arChart, arChartCanvas, 'Failed to load chart data. Please try again.');
            });
    }

    fetchAndUpdateAccountsReceivableChart();

    // Filter change functionality - using HTML select
    const filterSelect = document.getElementById('arFilterSelect');
    if (filterSelect) {
        filterSelect.addEventListener('change', function (e) {
            const selectedFilter = this.value;
            fetchAndUpdateAccountsReceivableChart(selectedFilter);
        });
    }

    // Three-dots menu toggle
    const menuButton = document.getElementById('arMenuButton');
    const dropdownMenu = document.getElementById('arDropdownMenu');

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
    const refreshBtn = document.getElementById('arRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Refresh the chart
            fetchAndUpdateAccountsReceivableChart();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('arExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Get current date and filter
            const currentDate = document.getElementById('ar_current_date').value;
            const filter = document.getElementById('arFilterSelect').value;

            // Create download URL with parameters
            const exportUrl = `/accounts-receivable/export-excel?current_date=${currentDate}&filter=${filter}`;

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
    const exportPdfBtn = document.getElementById('arExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Get current date and filter
            const currentDate = document.getElementById('ar_current_date').value;
            const filter = document.getElementById('arFilterSelect').value;

            // Create download URL with parameters
            const exportPdfUrl = `/accounts-receivable/export-pdf?current_date=${currentDate}&filter=${filter}`;

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

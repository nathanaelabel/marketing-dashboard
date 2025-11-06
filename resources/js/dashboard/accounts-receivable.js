document.addEventListener('DOMContentLoaded', function () {
    const arChartCanvas = document.getElementById('accountsReceivableChart');
    let arChart;

    if (!arChartCanvas) return;

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

    function fetchAndUpdateAccountsReceivableChart() {
        const arTotalEl = document.getElementById('arTotal');
        const arDateEl = document.getElementById('arDate');
        const chartContainer = document.getElementById('ar-chart-container');
        const refreshBtn = document.getElementById('arRefreshDataBtn');

        // Show loading state
        arTotalEl.textContent = 'Loading chart data...';
        if (chartContainer) {
            ChartHelper.showChartLoadingIndicator(chartContainer);
        }
        if (refreshBtn) {
            refreshBtn.disabled = true;
        }

        const url = arChartCanvas.dataset.url;
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

                arTotalEl.textContent = data.total;
                arDateEl.textContent = data.date;

                const yMax = ChartHelper.calculateYAxisMax(data.datasets, 5000000000);

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
                                    padding: 12
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
                                            // Format with Indonesian number format using dots as thousand separators
                                            const formattedValue = Math.round(context.parsed.y).toLocaleString('id-ID');
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
                                        return 'Total: ' + Math.round(total).toLocaleString('id-ID');
                                    }
                                }
                            },
                            datalabels: {
                                display: true,
                                color: '#333',
                                font: {
                                    weight: 'bold'
                                },
                                formatter: function (value, context) {
                                    if (value < 1800000000) { // Hide labels for values less than 1.5B
                                        return null;
                                    }
                                    const billions = value / 1000000000;
                                    const display = Math.round(billions * 10) / 10;
                                    if (display % 1 === 0) {
                                        return display.toFixed(0) + 'M';
                                    }
                                    return display.toFixed(1) + 'M';
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
                                        // Show clean numbers like 230, 200, 150 (value is in billions)
                                        const scaledValue = value / 1000000000;
                                        return Math.round(scaledValue);
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Miliar Rupiah'
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
                ChartHelper.showErrorMessage(arChart, arChartCanvas, 'Failed to load chart data. Please try again.');
            });
    }

    fetchAndUpdateAccountsReceivableChart();

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

            // Create download URL
            const exportUrl = '/accounts-receivable/export-excel';

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

            // Create download URL
            const exportPdfUrl = '/accounts-receivable/export-pdf';

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

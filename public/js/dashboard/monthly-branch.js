document.addEventListener('DOMContentLoaded', function () {
    let monthlyBranchChart = null;
    const ctx = document.getElementById('monthly-branch-chart');
    const yearSelect = document.getElementById('monthly-year-select');
    const branchSelect = document.getElementById('monthly-branch-select');
    const categorySelect = document.getElementById('monthly-category-select');

    if (!ctx) return;

    function updateMonthlyBranchChart(dataFromServer) {
        if (!dataFromServer) {
            console.error('No data received from server');
            return;
        }

        const chartLabels = dataFromServer.labels;
        const yAxisLabel = dataFromServer.yAxisLabel;
        const yAxisDivisor = dataFromServer.yAxisDivisor;
        const suggestedMax = dataFromServer.suggestedMax;

        // Use reusable growth label formatter with decimal precision (1 decimal place)
        const formatGrowthLabel = ChartHelper.createGrowthLabelFormatter(1);

        if (monthlyBranchChart) {
            monthlyBranchChart.data.labels = chartLabels;
            monthlyBranchChart.data.datasets = dataFromServer.datasets;
            monthlyBranchChart.options.scales.y.title.text = yAxisLabel;
            monthlyBranchChart.options.scales.y.suggestedMax = suggestedMax;
            monthlyBranchChart.options.scales.y.ticks.callback = function (value) {
                const scaledValue = value / yAxisDivisor;
                if (dataFromServer.yAxisUnit === 'B') {
                    if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                    return scaledValue.toFixed(1);
                } else {
                    return Math.round(scaledValue);
                }
            };
            monthlyBranchChart.options.plugins.datalabels.formatter = formatGrowthLabel;
            monthlyBranchChart.update();
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
            monthlyBranchChart = new Chart(ctx, {
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

    function fetchAndUpdateMonthlyChart(year, branch, category) {
        const url = `/monthly-branch/data?year=${year}&branch=${encodeURIComponent(branch)}&category=${encodeURIComponent(category)}`;
        const filterSelectors = ['monthly-year-select', 'monthly-branch-select', 'monthly-category-select'];
        const chartContainer = document.getElementById('monthly-branch-chart');

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

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);
                updateMonthlyBranchChart(data);
            })
            .catch(error => {
                console.error('Error fetching Monthly Branch data:', error);
                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);
                ChartHelper.showErrorMessage(monthlyBranchChart, ctx, 'Connection timed out. Try refreshing the page.');
            });
    }

    function loadBranches() {
        fetch('/monthly-branch/branches')
            .then(response => response.json())
            .then(branchOptions => {
                branchSelect.innerHTML = '';
                branchOptions.forEach(branchOption => {
                    const option = document.createElement('option');
                    option.value = branchOption.value;
                    option.textContent = branchOption.display;
                    branchSelect.appendChild(option);
                });

                // Set default to National if available
                const nationalOption = branchOptions.find(option => option.value === 'National');
                if (nationalOption) {
                    branchSelect.value = 'National';
                }

                // Trigger initial chart load
                triggerUpdate();
            })
            .catch(error => {
                console.error('Error loading branches:', error);
                branchSelect.innerHTML = '<option value="">Error loading branches</option>';
            });
    }

    const triggerUpdate = () => {
        const year = yearSelect.value;
        const branch = branchSelect.value;
        const category = categorySelect.value;

        if (year && branch && category) {
            fetchAndUpdateMonthlyChart(year, branch, category);
        }
    };

    // Event listeners
    yearSelect.addEventListener('change', triggerUpdate);
    branchSelect.addEventListener('change', triggerUpdate);
    categorySelect.addEventListener('change', triggerUpdate);

    // Initialize
    loadBranches();

    // Three-dots menu toggle
    const menuButton = document.getElementById('mbMenuButton');
    const dropdownMenu = document.getElementById('mbDropdownMenu');

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
    const refreshBtn = document.getElementById('mbRefreshDataBtn');
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
    const exportBtn = document.getElementById('mbExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentYear = yearSelect.value;
            const currentBranch = branchSelect.value;
            const currentCategory = categorySelect.value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportUrl = `/monthly-branch/export-excel?year=${currentYear}&branch=${encodeURIComponent(currentBranch)}&category=${encodeURIComponent(currentCategory)}`;

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
    const exportPdfBtn = document.getElementById('mbExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentYear = yearSelect.value;
            const currentBranch = branchSelect.value;
            const currentCategory = categorySelect.value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportPdfUrl = `/monthly-branch/export-pdf?year=${currentYear}&branch=${encodeURIComponent(currentBranch)}&category=${encodeURIComponent(currentCategory)}`;

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

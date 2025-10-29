document.addEventListener('DOMContentLoaded', function () {
    let branchGrowthChart;
    const chartContainer = document.getElementById('branch-growth-chart');
    const ctx = chartContainer.getContext('2d');
    const startYearSelect = document.getElementById('growth-start-year-select');
    const endYearSelect = document.getElementById('growth-end-year-select');
    const branchSelect = document.getElementById('growth-branch-select');
    const categorySelect = document.getElementById('growth-category-select');
    const typeSelect = document.getElementById('growth-type-select');

    if (!chartContainer) return;

    function updateBranchGrowthChart(dataFromServer) {
        if (!dataFromServer) {
            console.error('No data received from server');
            return;
        }

        const chartLabels = dataFromServer.labels;
        const yAxisLabel = dataFromServer.yAxisLabel;
        const yAxisDivisor = dataFromServer.yAxisDivisor;
        const suggestedMax = dataFromServer.suggestedMax;

        if (branchGrowthChart) {
            branchGrowthChart.data.labels = chartLabels;
            branchGrowthChart.data.datasets = dataFromServer.datasets;
            branchGrowthChart.options.scales.y.title.text = yAxisLabel;
            branchGrowthChart.options.scales.y.suggestedMax = suggestedMax;
            branchGrowthChart.options.scales.y.ticks.callback = function (value) {
                const scaledValue = value / yAxisDivisor;
                if (dataFromServer.yAxisUnit === 'B') {
                    if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                    return scaledValue.toFixed(1);
                } else {
                    return Math.round(scaledValue);
                }
            };
            branchGrowthChart.update();
        } else {
            // Register custom legend margin plugin
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

            Chart.register(LegendMargin, ChartDataLabels);
            branchGrowthChart = new Chart(ctx, {
                type: 'line',
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
                        // extra spacing below legend
                        legendMargin: {
                            margin: 10
                        },
                        datalabels: {
                            display: function (context) {
                                // Only show labels for non-zero values
                                return context.dataset.data[context.dataIndex] > 0;
                            },
                            anchor: 'end',
                            align: 'top',
                            offset: 4,
                            color: function (context) {
                                return context.dataset.borderColor;
                            },
                            font: {
                                size: 11,
                                weight: 'bold'
                            },
                            formatter: function (value, context) {
                                // Use formatted data from dataset
                                if (context.dataset.formattedData && context.dataset.formattedData[context.dataIndex]) {
                                    return context.dataset.formattedData[context.dataIndex];
                                }
                                return value;
                            }
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
                                        // Show raw values in tooltips
                                        label += new Intl.NumberFormat('id-ID', {
                                            minimumFractionDigits: 0
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                },
                                afterLabel: function (context) {
                                    // Calculate growth percentage compared to previous year
                                    const currentDatasetIndex = context.datasetIndex;
                                    const monthIndex = context.dataIndex;

                                    // Only show growth if there's a previous year dataset
                                    if (currentDatasetIndex > 0) {
                                        const currentValue = context.parsed.y;
                                        const previousDataset = context.chart.data.datasets[currentDatasetIndex - 1];
                                        const previousValue = previousDataset.data[monthIndex];

                                        if (previousValue > 0 && currentValue !== null) {
                                            const growth = ((currentValue - previousValue) / previousValue) * 100;
                                            const growthSign = growth >= 0 ? '+' : '';
                                            return 'Growth: ' + growthSign + growth.toFixed(2) + '%';
                                        }
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
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
                    },
                    elements: {
                        point: {
                            radius: 4,
                            hoverRadius: 6
                        }
                    }
                }
            });
        }
    }


    function showNoDataMessage(message) {
        if (branchGrowthChart) {
            branchGrowthChart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            branchGrowthChart.data.datasets = [];
            branchGrowthChart.update();
        }

        // Show no data message in the chart container
        const chartContainerElement = chartContainer.parentElement;
        const existingMessage = chartContainerElement.querySelector('.no-data-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'no-data-message text-center p-4 text-gray-600';
        messageDiv.innerHTML = `<i class="fas fa-info-circle mr-2"></i>${message}`;
        chartContainerElement.appendChild(messageDiv);
    }

    function clearMessages() {
        const chartContainerElement = chartContainer.parentElement;
        const errorMessage = chartContainerElement.querySelector('.error-message');
        const noDataMessage = chartContainerElement.querySelector('.no-data-message');

        if (errorMessage) errorMessage.remove();
        if (noDataMessage) noDataMessage.remove();
    }

    function fetchAndUpdateGrowthChart(branch, startYear, endYear, category, type) {
        const url = `/branch-growth/data?branch=${encodeURIComponent(branch)}&start_year=${startYear}&end_year=${endYear}&category=${encodeURIComponent(category)}&type=${encodeURIComponent(type)}`;
        const filterSelectors = ['growth-start-year-select', 'growth-end-year-select', 'growth-branch-select', 'growth-category-select', 'growth-type-select'];

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
                // Check if the response contains an error
                if (data.error) {
                    console.error('Server error:', data.error);
                    ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                    ChartHelper.enableFilters(filterSelectors);
                    showErrorMessage(data.error);
                    return;
                }

                // Check if there's a message indicating no data
                if (data.message) {
                    console.log('Server message:', data.message);
                    ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                    ChartHelper.enableFilters(filterSelectors);
                    showNoDataMessage(data.message);
                    return;
                }

                clearMessages();
                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);
                updateBranchGrowthChart(data);
            })
            .catch(error => {
                console.error('Error fetching Branch Growth data:', error);
                ChartHelper.hideChartLoadingIndicator(chartContainer.parentElement);
                ChartHelper.enableFilters(filterSelectors);
                ChartHelper.showErrorMessage(branchGrowthChart, ctx, 'Connection timed out. Try refreshing the page.');
            });
    }

    function loadBranches() {
        fetch('/branch-growth/branches')
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

    function validateYearRange() {
        const startYear = parseInt(startYearSelect.value);
        const endYear = parseInt(endYearSelect.value);
        const currentYear = new Date().getFullYear();

        // Ensure start year doesn't exceed end year
        if (startYear > endYear) {
            endYearSelect.value = startYear + 1;
        }

        // Ensure end year doesn't exceed current year (2025)
        if (endYear > currentYear) {
            endYearSelect.value = currentYear;
        }

        // Update end year options based on start year
        const endYearOptions = endYearSelect.querySelectorAll('option');
        endYearOptions.forEach(option => {
            const year = parseInt(option.value);
            option.disabled = year <= startYear || year > currentYear;
        });

        // Update start year options based on end year
        const startYearOptions = startYearSelect.querySelectorAll('option');
        startYearOptions.forEach(option => {
            const year = parseInt(option.value);
            option.disabled = year >= parseInt(endYearSelect.value);
        });
    }

    const triggerUpdate = () => {
        const branch = branchSelect.value;
        const startYear = startYearSelect.value;
        const endYear = endYearSelect.value;
        const category = categorySelect.value;
        const type = typeSelect.value;

        if (branch && startYear && endYear && category) {
            fetchAndUpdateGrowthChart(branch, startYear, endYear, category, type);
        }
    };

    // Event listeners
    branchSelect.addEventListener('change', triggerUpdate);
    startYearSelect.addEventListener('change', () => {
        validateYearRange();
        triggerUpdate();
    });
    endYearSelect.addEventListener('change', () => {
        validateYearRange();
        triggerUpdate();
    });
    categorySelect.addEventListener('change', triggerUpdate);
    typeSelect.addEventListener('change', triggerUpdate);

    // Initialize year validation
    validateYearRange();

    // Initialize
    loadBranches();

    // Three-dots menu functionality
    const menuButton = document.getElementById('bgMenuButton');
    const dropdownMenu = document.getElementById('bgDropdownMenu');

    if (menuButton && dropdownMenu) {
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
    const refreshBtn = document.getElementById('bgRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Trigger chart update
            triggerUpdate();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('bgExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartYear = startYearSelect.value;
            const currentEndYear = endYearSelect.value;
            const currentBranch = branchSelect.value;
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
            const exportUrl = `/branch-growth/export-excel?start_year=${currentStartYear}&end_year=${currentEndYear}&branch=${encodeURIComponent(currentBranch)}&category=${encodeURIComponent(currentCategory)}&type=${encodeURIComponent(currentType)}`;

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
    const exportPdfBtn = document.getElementById('bgExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartYear = startYearSelect.value;
            const currentEndYear = endYearSelect.value;
            const currentBranch = branchSelect.value;
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
            const exportPdfUrl = `/branch-growth/export-pdf?start_year=${currentStartYear}&end_year=${currentEndYear}&branch=${encodeURIComponent(currentBranch)}&category=${encodeURIComponent(currentCategory)}&type=${encodeURIComponent(currentType)}`;

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

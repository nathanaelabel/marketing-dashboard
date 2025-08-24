document.addEventListener('DOMContentLoaded', function () {
    let branchGrowthChart = null;
    const ctx = document.getElementById('branch-growth-chart');
    const branchSelect = document.getElementById('growth-branch-select');
    const startYearSelect = document.getElementById('growth-start-year-select');
    const endYearSelect = document.getElementById('growth-end-year-select');
    const categorySelect = document.getElementById('growth-category-select');

    if (!ctx) return;

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

    function fetchAndUpdateGrowthChart(branch, startYear, endYear, category) {
        const url = `/branch-growth/data?branch=${encodeURIComponent(branch)}&start_year=${startYear}&end_year=${endYear}&category=${encodeURIComponent(category)}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                updateBranchGrowthChart(data);
            })
            .catch(error => {
                console.error('Error fetching Branch Growth data:', error);
                if (branchGrowthChart) {
                    branchGrowthChart.data.labels = [];
                    branchGrowthChart.data.datasets = [];
                    branchGrowthChart.update();
                }
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

        if (branch && startYear && endYear && category) {
            fetchAndUpdateGrowthChart(branch, startYear, endYear, category);
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

    // Initialize year validation
    validateYearRange();

    // Initialize
    loadBranches();
});

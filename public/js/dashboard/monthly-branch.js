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
                    return (growth >= 0 ? 'Rp ' : '') + growth.toFixed(0) + '%';
                }
            }
            return null;
        };

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
        const url = `/api/monthly-branch-data?year=${year}&branch=${encodeURIComponent(branch)}&category=${encodeURIComponent(category)}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                updateMonthlyBranchChart(data);
            })
            .catch(error => {
                console.error('Error fetching Monthly Branch data:', error);
                if (monthlyBranchChart) {
                    monthlyBranchChart.data.labels = [];
                    monthlyBranchChart.data.datasets = [];
                    monthlyBranchChart.update();
                }
            });
    }

    function loadBranches() {
        fetch('/api/monthly-branch-branches')
            .then(response => response.json())
            .then(branchOptions => {
                branchSelect.innerHTML = '';
                branchOptions.forEach(branchOption => {
                    const option = document.createElement('option');
                    option.value = branchOption.value;
                    option.textContent = branchOption.display;
                    branchSelect.appendChild(option);
                });

                // Set default to PWM Jakarta if available
                const jakartaOption = branchOptions.find(option => option.value === 'PWM Jakarta');
                if (jakartaOption) {
                    branchSelect.value = 'PWM Jakarta';
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
});

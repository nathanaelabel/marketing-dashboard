document.addEventListener('DOMContentLoaded', function() {
    let nationalYearlyChart = null;
    const ctx = document.getElementById('national-yearly-chart');
    const yearSelect = document.getElementById('yearly-year-select');
    const categorySelect = document.getElementById('yearly-category-select');
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

        const formatValueWithUnit = (value) => {
            if (value === 0) return null;
            const scaledValue = value / yAxisDivisor;
            if (dataFromServer.yAxisUnit === 'B') {
                const rounded = Math.round(scaledValue * 10) / 10;
                const display = (rounded % 1 === 0) ? rounded.toFixed(0) : rounded.toFixed(1);
                return display + 'B';
            }
            return Math.round(scaledValue) + 'M';
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
            Chart.register(ChartDataLabels);
            nationalYearlyChart = new Chart(ctx, {
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
                            display: false
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

    function fetchAndUpdateYearlyChart(year, category) {
        const url = `/api/national-yearly-data?year=${year}&category=${encodeURIComponent(category)}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                updateNationalYearlyChart(data);
            })
            .catch(error => {
                console.error('Error fetching National Yearly data:', error);
                if (nationalYearlyChart) {
                    nationalYearlyChart.data.labels = [];
                    nationalYearlyChart.data.datasets = [];
                    nationalYearlyChart.update();
                }
            });
    }

    const triggerUpdate = () => {
        const year = yearSelect.value;
        const category = categorySelect.value;
        
        // Update year labels
        previousYearLabel.textContent = (year - 1);
        currentYearLabel.textContent = year;
        
        fetchAndUpdateYearlyChart(year, category);
    };

    // Event listeners
    yearSelect.addEventListener('change', triggerUpdate);
    categorySelect.addEventListener('change', triggerUpdate);

    // Initialize
    triggerUpdate();
});

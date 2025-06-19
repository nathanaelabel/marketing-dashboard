<div class="p-6 bg-white border-b border-gray-200">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Accounts Receivable</h3>
            <p id="arTotalOverdueDisplay" class="mt-2 mb-2 text-1xl font-medium text-gray-700">Rp 0</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">{{ $currentDateFormatted }}</p>
        </div>
    </div>

    <canvas id="accountsReceivableChart" style="max-height: 500px; width: 100%;"></canvas>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let arChartInstance;
        const arTotalOverdueDisplay = document.getElementById('arTotalOverdueDisplay');
        const arLastUpdateDisplay = document.getElementById('arLastUpdateDisplay');
        const arChartCanvas = document.getElementById('accountsReceivableChart');

        function updateARDisplayAndChart(dataFromServer) {
            if (!dataFromServer) {
                arTotalOverdueDisplay.textContent = 'Error loading data';
                console.log('AR Data as of: Error');
                return;
            }

            arTotalOverdueDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(dataFromServer.totalOverdue);
            console.log(dataFromServer.lastUpdate ? 'AR Data as of: ' + dataFromServer.lastUpdate : 'AR Data as of: Not available');

            // Y-axis scaling parameters are now provided by the server
            const yAxisLabel = dataFromServer.yAxisLabel;
            const yAxisDivisor = dataFromServer.yAxisDivisor;
            const suggestedMax = dataFromServer.suggestedMax;

            const chartConfig = {
                type: 'bar',
                data: {
                    labels: dataFromServer.labels,
                    datasets: [{
                            label: '1 - 30 Days',
                            data: dataFromServer.data_1_30,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)', // Green
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: '31 - 60 Days',
                            data: dataFromServer.data_31_60,
                            backgroundColor: 'rgba(153, 102, 255, 0.7)', // Purple
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        },
                        {
                            label: '61 - 90 Days',
                            data: dataFromServer.data_61_90,
                            backgroundColor: 'rgba(255, 159, 64, 0.7)', // Orange
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        },
                        {
                            label: '> 90 Days',
                            data: dataFromServer.data_over_90,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)', // Red
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'center',
                            font: {
                                weight: 'bold',
                                size: 10
                            },
                            formatter: (value, context) => {
                                if (value === 0) return null;

                                // Hide label if it's too small compared to the max value of the axis
                                if (suggestedMax > 0 && (value / suggestedMax) < 0.015) {
                                    return null;
                                }
                                const scaledValue = value / yAxisDivisor;
                                if (dataFromServer.yAxisUnit === 'B') {
                                    return (Math.round(scaledValue * 10) / 10).toFixed(1);
                                } else {
                                    return Math.round(scaledValue);
                                }
                            },
                            color: function(context) {
                                const bgColor = context.dataset.backgroundColor;
                                if (bgColor === 'rgba(153, 102, 255, 0.7)' || bgColor === 'rgba(255, 99, 132, 0.7)') {
                                    if (context.datasetIndex === 1 /* 31-60 days */ || context.datasetIndex === 3 /* >90 days, if it were dark */ ) {}
                                }
                                return '#333';
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            suggestedMax: dataFromServer.suggestedMax,
                            ticks: {
                                callback: function(value) {
                                    const scaledValue = value / dataFromServer.yAxisDivisor;
                                    if (dataFromServer.yAxisUnit === 'B') {
                                        if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                                        return scaledValue.toFixed(1);
                                    } else {
                                        return Math.round(scaledValue);
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: dataFromServer.yAxisLabel
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                },
                plugins: [ChartDataLabels]
            };

            if (arChartInstance) {
                arChartInstance.data.labels = dataFromServer.labels;
                arChartInstance.data.datasets[0].data = dataFromServer.data_1_30;
                arChartInstance.data.datasets[1].data = dataFromServer.data_31_60;
                arChartInstance.data.datasets[2].data = dataFromServer.data_61_90;
                arChartInstance.data.datasets[3].data = dataFromServer.data_over_90;

                // Update dynamic axis options using server-provided data
                const yAxisLabel = dataFromServer.yAxisLabel;
                const yAxisDivisor = dataFromServer.yAxisDivisor;
                const suggestedMax = dataFromServer.suggestedMax;

                arChartInstance.options.scales.y.title.text = yAxisLabel;
                arChartInstance.options.scales.y.suggestedMax = suggestedMax;
                arChartInstance.options.scales.y.ticks.callback = function(value) {
                    const scaledValue = value / yAxisDivisor;
                    if (dataFromServer.yAxisUnit === 'B') {
                        if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                        return scaledValue.toFixed(1);
                    } else {
                        return Math.round(scaledValue);
                    }
                };
                // Ensure datalabels font size is set if not already
                arChartInstance.options.plugins.datalabels.font.size = 10;
                arChartInstance.options.plugins.datalabels.formatter = (value, context) => {
                    if (value === 0) return null;
                    if (suggestedMax > 0 && (value / suggestedMax) < 0.015) {
                        return null;
                    }
                    const scaledValue = value / yAxisDivisor;
                    if (dataFromServer.yAxisUnit === 'B') {
                        return (Math.round(scaledValue * 10) / 10).toFixed(1);
                    } else {
                        return Math.round(scaledValue);
                    }
                };
                arChartInstance.update();
            } else {
                if (!arChartCanvas) return;
                const ctx = arChartCanvas.getContext('2d');
                arChartInstance = new Chart(ctx, chartConfig);
            }
        }

        function fetchAndUpdateARChart() {
            arTotalOverdueDisplay.textContent = 'Loading...';
            console.log('Fetching AR data...');
            const url = `{{ route('accounts-receivable.data') }}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    updateARDisplayAndChart(data);
                })
                .catch(error => {
                    console.error('AR: Error fetching Accounts Receivable data:', error);
                    arTotalOverdueDisplay.textContent = 'Error loading data';
                    if (arChartInstance) {
                        arChartInstance.data.labels = [];
                        arChartInstance.data.datasets.forEach(dataset => dataset.data = []);
                        arChartInstance.update();
                    }
                });
        }

        fetchAndUpdateARChart();
    });
</script>
@endpush
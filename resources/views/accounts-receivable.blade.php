<div class="p-6 bg-white border-b border-gray-200">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Accounts Receivable</h3>
            <p id="arTotalOverdueDisplay" class="mt-2 mb-2 text-1xl font-medium text-gray-700">Rp 0</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">{{ $currentDateFormatted }}</p>
            <p id="arLastUpdateDisplay" class="text-xs text-gray-400">Data as of: Loading...</p>
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

        function formatARNumber(num) {
            if (num >= 1e9) {
                return (num / 1e9).toFixed(2) + 'B';
            }
            if (num >= 1e6) {
                return (num / 1e6).toFixed(2) + 'M';
            }
            if (num >= 1e3) {
                return (num / 1e3).toFixed(2) + 'K';
            }
            return num;
        }

        function updateARDisplayAndChart(dataFromServer) {
            console.log('AR: updateARDisplayAndChart received:', dataFromServer); // DEBUG
            if (!dataFromServer) {
                arTotalOverdueDisplay.textContent = 'Error loading data';
                arLastUpdateDisplay.textContent = 'Data as of: Error';
                return;
            }

            arTotalOverdueDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(dataFromServer.totalOverdue);
            arLastUpdateDisplay.textContent = 'Data as of: ' + dataFromServer.lastUpdate;

            // Calculate dynamic scaling for AR chart
            let maxIndividualStackTotal = 0;
            let sumOfBranchStackTotals = 0;
            let numberOfBranchesWithData = 0;

            if (dataFromServer.labels && dataFromServer.labels.length > 0) {
                dataFromServer.labels.forEach((label, index) => {
                    const stackTotal =
                        (dataFromServer.data_1_30[index] || 0) +
                        (dataFromServer.data_31_60[index] || 0) +
                        (dataFromServer.data_61_90[index] || 0) +
                        (dataFromServer.data_over_90[index] || 0);

                    if (stackTotal > 0) {
                        sumOfBranchStackTotals += stackTotal;
                        numberOfBranchesWithData++;
                    }
                    if (stackTotal > maxIndividualStackTotal) {
                        maxIndividualStackTotal = stackTotal;
                    }
                });
            }

            const averageARStackTotal = numberOfBranchesWithData > 0 ? sumOfBranchStackTotals / numberOfBranchesWithData : 0;

            const useARBillions = averageARStackTotal >= 1000000000; // Threshold for billions
            const arDivisor = useARBillions ? 1000000000 : 1000000;
            const yAxisARTitle = useARBillions ? 'Billion Rupiah (Rp)' : 'Million Rupiah (Rp)';

            // Calculate suggestedMax for y-axis.
            // Aim for a y-axis max of 100 (scaled units), unless data + padding exceeds this.
            let finalSuggestedMaxVal;
            let desiredMaxRaw;
            if (useARBillions) {
                desiredMaxRaw = 100 * 1000000000; // 100 Billion
            } else {
                desiredMaxRaw = 100 * 1000000; // 100 Million
            }

            if (maxIndividualStackTotal === 0) {
                finalSuggestedMaxVal = desiredMaxRaw; // If no data, axis goes to 100 (scaled)
            } else if (maxIndividualStackTotal * 1.05 > desiredMaxRaw) {
                // If max data (with 5% padding) exceeds our desired 100 (scaled) limit,
                // then we must use a larger max, based on the actual data + 20% padding.
                finalSuggestedMaxVal = maxIndividualStackTotal * 1.2;
            } else {
                // Otherwise, the data fits comfortably, so set the axis max to 100 (scaled).
                finalSuggestedMaxVal = desiredMaxRaw;
            }

            // Safety fallback if calculations result in zero but there's data, or ensure a minimum if no data and desiredMaxRaw was 0.
            if (finalSuggestedMaxVal === 0 && maxIndividualStackTotal > 0) {
                finalSuggestedMaxVal = maxIndividualStackTotal * 1.2;
            } else if (finalSuggestedMaxVal === 0 && maxIndividualStackTotal === 0) {
                finalSuggestedMaxVal = useARBillions ? 1000000000 : 1000000; // Default to 1 unit (1B or 1M)
            }
            const arSuggestedMaxVal = finalSuggestedMaxVal; // Use this for the chart

            console.log(`AR Scaling Pre-Config: Title: ${yAxisARTitle}, Divisor: ${arDivisor}, MaxStack: ${maxIndividualStackTotal}, SuggestedMax: ${arSuggestedMaxVal}`); // DEBUG

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
                            formatter: (value, context) => {
                                const currentArDivisor = arDivisor;
                                const currentArSuggestedMaxVal = arSuggestedMaxVal;

                                if (value === 0) return null;

                                if (currentArSuggestedMaxVal > 0 && (value / currentArSuggestedMaxVal) < 0.015) {
                                    return null;
                                }
                                return Math.round(value / currentArDivisor);
                            },
                            font: {
                                weight: 'bold',
                                size: 10
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
                            suggestedMax: arSuggestedMaxVal, // Apply dynamic max
                            ticks: {
                                callback: function(value) {
                                    // Ensure arDivisor is from the surrounding scope
                                    const currentArDivisor = arDivisor;
                                    console.log(`AR Y-axis tick value: ${value}, arDivisor: ${currentArDivisor}, scaled: ${value / currentArDivisor}`); // DEBUG
                                    return value / currentArDivisor;
                                }
                            },
                            title: {
                                display: true,
                                text: yAxisARTitle
                            } // Apply dynamic title
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                },
                plugins: [ChartDataLabels] // Ensure ChartDataLabels is registered globally or passed here
            };

            if (arChartInstance) {
                arChartInstance.data.labels = dataFromServer.labels;
                arChartInstance.data.datasets[0].data = dataFromServer.data_1_30;
                arChartInstance.data.datasets[1].data = dataFromServer.data_31_60;
                arChartInstance.data.datasets[2].data = dataFromServer.data_61_90;
                arChartInstance.data.datasets[3].data = dataFromServer.data_over_90;
                // Update dynamic axis options before redrawing
                arChartInstance.options.scales.y.title.text = yAxisARTitle;
                arChartInstance.options.scales.y.suggestedMax = arSuggestedMaxVal;
                console.log(`AR Scaling Pre-Update: Title: ${yAxisARTitle}, Divisor: ${arDivisor}, MaxStack: ${maxIndividualStackTotal}, SuggestedMax: ${arSuggestedMaxVal}`); // DEBUG
                arChartInstance.options.scales.y.ticks.callback = function(value) {
                    // Ensure arDivisor is from the surrounding scope for updates too
                    const currentArDivisor = arDivisor;
                    console.log(`AR Y-axis tick (update) value: ${value}, arDivisor: ${currentArDivisor}, scaled: ${value / currentArDivisor}`); // DEBUG
                    return value / currentArDivisor;
                };
                arChartInstance.options.plugins.datalabels.anchor = 'center';
                arChartInstance.options.plugins.datalabels.align = 'center';
                arChartInstance.options.plugins.datalabels.font.size = 10;
                arChartInstance.options.plugins.datalabels.formatter = (value, context) => {
                    const currentArDivisor = arDivisor;
                    const currentArSuggestedMaxVal = arSuggestedMaxVal;
                    if (value === 0) return null;
                    if (currentArSuggestedMaxVal > 0 && (value / currentArSuggestedMaxVal) < 0.015) {
                        return null;
                    }
                    return Math.round(value / currentArDivisor);
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
            arLastUpdateDisplay.textContent = 'Data as of: Loading...';
            const url = `{{ route('accounts-receivable.data') }}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('AR: Fetched data from server:', data);
                    updateARDisplayAndChart(data);
                })
                .catch(error => {
                    console.error('AR: Error fetching Accounts Receivable data:', error);
                    console.error('Error fetching Accounts Receivable data:', error);
                    arTotalOverdueDisplay.textContent = 'Error loading data';
                    arLastUpdateDisplay.textContent = 'Data as of: Error';
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
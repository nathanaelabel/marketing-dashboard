<div class="p-6 text-gray-900">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">National Revenue</h3>
            <p id="nationalTotalRevenueDisplay" class="mt-2 mb-2 text-1xl font-medium text-gray-700">Rp 0</p> <!-- Updated by JS -->
        </div>
        <form id="dateFilterForm" method="GET" action="{{ route('dashboard') }}" class="flex items-end space-x-3">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                <div class="relative">
                    <input type="text" name="start_date" id="start_date" value="{{ $startDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="flex items-center h-8 text-gray-400">
                <span class="text-xs">to</span>
            </div>
            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                <div class="relative">
                    <input type="text" name="end_date" id="end_date" value="{{ $endDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <canvas id="revenueChart" style="max-height: 300px; width: 100%;"></canvas>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let nationalRevenueChartInstance;
        const nationalTotalRevenueDisplay = document.getElementById('nationalTotalRevenueDisplay');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const revenueChartCanvas = document.getElementById('revenueChart');

        function updateNationalRevenueDisplayAndChart(dataFromServer) {
            if (!dataFromServer) {
                nationalTotalRevenueDisplay.textContent = 'Error loading data';
                return;
            }

            nationalTotalRevenueDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(dataFromServer.totalRevenue);

            const chartLabels = dataFromServer.labels;
            const chartDataValues = dataFromServer.datasets[0].data;

            let sbyValue = 0;
            const sbyIndex = chartLabels.indexOf('SBY');
            if (sbyIndex !== -1) sbyValue = chartDataValues[sbyIndex];

            let jktValue = 0;
            const jktIndex = chartLabels.indexOf('JKT');
            if (jktIndex !== -1) jktValue = chartDataValues[jktIndex];

            let bdgValue = 0;
            const bdgIndex = chartLabels.indexOf('BDG');
            if (bdgIndex !== -1) bdgValue = chartDataValues[bdgIndex];

            const totalForAvg = sbyValue + jktValue + bdgValue;
            const numBranchesForAvg = (sbyValue > 0 ? 1 : 0) + (jktValue > 0 ? 1 : 0) + (bdgValue > 0 ? 1 : 0);
            const averageRevenue = numBranchesForAvg > 0 ? totalForAvg / numBranchesForAvg : 0;

            const useBillions = averageRevenue >= 1000000000;
            const yAxisLabel = useBillions ? 'Billion Rupiah (Rp)' : 'Million Rupiah (Rp)';
            const divisor = useBillions ? 1000000000 : 1000000;
            const maxValue = chartDataValues.length > 0 ? Math.max(...chartDataValues) : 0;
            const suggestedMaxVal = maxValue > 0 ? (maxValue / divisor) * 1.2 * divisor : (useBillions ? 1000000000 : 1000000);

            if (nationalRevenueChartInstance) {
                nationalRevenueChartInstance.data.labels = chartLabels;
                nationalRevenueChartInstance.data.datasets[0].data = chartDataValues;
                nationalRevenueChartInstance.options.scales.y.title.text = yAxisLabel;
                nationalRevenueChartInstance.options.scales.y.suggestedMax = suggestedMaxVal;
                nationalRevenueChartInstance.options.scales.y.ticks.callback = function(value) {
                    return value / divisor;
                };
                nationalRevenueChartInstance.options.plugins.datalabels.formatter = (value) => Math.round(value / divisor);
                nationalRevenueChartInstance.update();
            } else {
                if (!revenueChartCanvas) return;
                const ctx = revenueChartCanvas.getContext('2d');
                Chart.register(ChartDataLabels);
                nationalRevenueChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: dataFromServer.datasets[0].label || 'Revenue',
                            data: chartDataValues,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                        }]
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
                                    label: function(context) {
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
                                formatter: (value) => Math.round(value / divisor),
                                font: {
                                    weight: 'bold'
                                },
                                color: '#444'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                suggestedMax: suggestedMaxVal,
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
                                    callback: function(value) {
                                        return value / divisor;
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

        function fetchAndUpdateNationalRevenueChart(startDate, endDate) {
            nationalTotalRevenueDisplay.textContent = 'Loading...'; // Indicate loading
            const url = `{{ route('national-revenue.data') }}?start_date=${startDate}&end_date=${endDate}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    updateNationalRevenueDisplayAndChart(data);
                    // Optionally update flatpickr inputs if server modified dates, though unlikely here
                    // startDateInput._flatpickr.setDate(data.startDate, false);
                    // endDateInput._flatpickr.setDate(data.endDate, false);
                })
                .catch(error => {
                    console.error('Error fetching National Revenue data:', error);
                    nationalTotalRevenueDisplay.textContent = 'Error loading data';
                    // Optionally clear or show error state in chart
                    if (nationalRevenueChartInstance) {
                        nationalRevenueChartInstance.data.labels = [];
                        nationalRevenueChartInstance.data.datasets[0].data = [];
                        nationalRevenueChartInstance.update();
                    }
                });
        }

        flatpickr('.flatpickr-input', {
            altInput: true,
            altFormat: "d-m-Y",
            dateFormat: "Y-m-d",
            onChange: function(selectedDates, dateStr, instance) {
                const currentStartDate = startDateInput.value;
                const currentEndDate = endDateInput.value;
                if (currentStartDate && currentEndDate) {
                    fetchAndUpdateNationalRevenueChart(currentStartDate, currentEndDate);
                }
            }
        });

        // Initial data load using values from PHP for the input fields
        const initialStartDate = startDateInput.value;
        const initialEndDate = endDateInput.value;
        if (initialStartDate && initialEndDate) {
            fetchAndUpdateNationalRevenueChart(initialStartDate, initialEndDate);
        }
    });
</script>
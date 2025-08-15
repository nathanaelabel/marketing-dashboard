<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('partials.national-revenue')
            </div>

            <div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('partials.accounts-receivable')
            </div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js?v=1.1"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0?v=1.1"></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr?v=1.1"></script>
            <script>
                console.log('Script version 2.0 loaded');
                const ChartHelper = {
                    calculateYAxisMax(datasets, increment) {
                        let maxTotal = 0;
                        if (datasets.length > 0) {
                            const dataLength = datasets[0].data.length;
                            for (let i = 0; i < dataLength; i++) {
                                let total = 0;
                                for (const dataset of datasets) {
                                    total += dataset.data[i] || 0;
                                }
                                if (total > maxTotal) {
                                    maxTotal = total;
                                }
                            }
                        }
                        return Math.ceil(maxTotal / increment) * increment;
                    },

                    formatCurrency(value, divisor) {
                        const scaledValue = value / divisor;
                        if (scaledValue >= 1) {
                            return scaledValue.toFixed(0);
                        } else if (scaledValue > 0) {
                            return scaledValue.toFixed(1);
                        } else {
                            return '0';
                        }
                    }
                };

                document.addEventListener('DOMContentLoaded', function() {
                    // --- National Revenue Chart --- //
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

                        if (nationalRevenueChartInstance) {
                            nationalRevenueChartInstance.data.labels = chartLabels;
                            nationalRevenueChartInstance.data.datasets[0].data = chartDataValues;
                            nationalRevenueChartInstance.options.scales.y.title.text = yAxisLabel;
                            nationalRevenueChartInstance.options.scales.y.suggestedMax = suggestedMax;
                            nationalRevenueChartInstance.options.scales.y.ticks.callback = function(value) {
                                const scaledValue = value / yAxisDivisor;
                                if (dataFromServer.yAxisUnit === 'B') {
                                    if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                                    return scaledValue.toFixed(1);
                                } else {
                                    return Math.round(scaledValue);
                                }
                            };
                            nationalRevenueChartInstance.options.plugins.datalabels.formatter = formatValueWithUnit;

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
                                            formatter: formatValueWithUnit,
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
                                                callback: function(value) {
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

                    function fetchAndUpdateNationalRevenueChart(startDate, endDate) {
                        if (!nationalTotalRevenueDisplay) return;
                        nationalTotalRevenueDisplay.textContent = 'Loading...';
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
                            })
                            .catch(error => {
                                console.error('Error fetching National Revenue data:', error);
                                nationalTotalRevenueDisplay.textContent = 'Error loading data';
                                if (nationalRevenueChartInstance) {
                                    nationalRevenueChartInstance.data.labels = [];
                                    nationalRevenueChartInstance.data.datasets[0].data = [];
                                    nationalRevenueChartInstance.update();
                                }
                            });
                    }

                    if (revenueChartCanvas) {
                        let startDatePicker, endDatePicker;

                        const triggerUpdate = () => {
                            const currentStartDate = startDateInput.value;
                            const currentEndDate = endDateInput.value;
                            if (currentStartDate && currentEndDate) {
                                fetchAndUpdateNationalRevenueChart(currentStartDate, currentEndDate);
                            }
                        };

                        startDatePicker = flatpickr(startDateInput, {
                            altInput: true,
                            altFormat: "d-m-Y",
                            dateFormat: "Y-m-d",
                            maxDate: endDateInput.value,
                            onChange: function(selectedDates, dateStr, instance) {
                                if (endDatePicker) {
                                    endDatePicker.set('minDate', selectedDates[0]);
                                }
                                triggerUpdate();
                            }
                        });

                        endDatePicker = flatpickr(endDateInput, {
                            altInput: true,
                            altFormat: "d-m-Y",
                            dateFormat: "Y-m-d",
                            maxDate: "today",
                            onChange: function(selectedDates, dateStr, instance) {
                                if (startDatePicker) {
                                    startDatePicker.set('maxDate', selectedDates[0]);
                                }
                                triggerUpdate();
                            }
                        });

                        const initialStartDate = startDateInput.value;
                        const initialEndDate = endDateInput.value;
                        if (initialStartDate && initialEndDate) {
                            fetchAndUpdateNationalRevenueChart(initialStartDate, initialEndDate);
                        }
                    }

                    // --- Accounts Receivable Chart --- //
                    const arChartCanvas = document.getElementById('accountsReceivableChart');
                    let arChart;

                    function fetchAndUpdateAccountsReceivableChart() {
                        if (!arChartCanvas) return;

                        const arTotalEl = document.getElementById('arTotal');
                        const arDateEl = document.getElementById('arDate');
                        arTotalEl.textContent = 'Loading...';

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
                                            },
                                            tooltip: {
                                                mode: 'index',
                                                intersect: false,
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.dataset.label || '';
                                                        if (label) {
                                                            label += ': ';
                                                        }
                                                        if (context.parsed.y !== null) {
                                                            label += new Intl.NumberFormat('id-ID', {
                                                                style: 'currency',
                                                                currency: 'IDR',
                                                                minimumFractionDigits: 0
                                                            }).format(context.parsed.y);
                                                        }
                                                        return label;
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                display: true,
                                                color: '#333',
                                                font: {
                                                    weight: 'bold'
                                                },
                                                formatter: function(value, context) {
                                                    if (value < 1500000000) { // Hide labels for values less than 1.5B
                                                        return null;
                                                    }
                                                    const billions = value / 1000000000;
                                                    const display = Math.round(billions * 10) / 10;
                                                    if (display % 1 === 0) {
                                                        return display.toFixed(0) + 'B';
                                                    }
                                                    return display.toFixed(1) + 'B';
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
                                                    callback: function(value) {
                                                        return ChartHelper.formatCurrency(value, 1000000000);
                                                    }
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Billion Rupiah (Rp)'
                                                }
                                            }
                                        }
                                    }
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching Accounts Receivable data:', error);
                                if (arTotalEl) arTotalEl.textContent = 'Error loading data.';
                            });
                    }

                    fetchAndUpdateAccountsReceivableChart();
                });
            </script>
            @endpush

        </div>
    </div>

</x-app-layout>
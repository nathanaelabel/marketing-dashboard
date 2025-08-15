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

            @include('partials.sales-metrics')

            <div id="js-data"
                data-locations-url="{{ route('locations') }}"
                data-sales-metrics-url="{{ route('sales.metrics.data') }}"></div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js?v=1.1"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0?v=1.1"></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr?v=1.1"></script>
            <script>
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

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const jsData = document.getElementById('js-data');
                    const locationsUrl = jsData.dataset.locationsUrl;
                    const salesMetricsUrl = jsData.dataset.salesMetricsUrl;

                    // Selectors for the new elements
                    const locationFilter = document.getElementById('location-filter');
                    const startDateFilter = document.getElementById('start-date-filter');
                    const endDateFilter = document.getElementById('end-date-filter');

                    const totalSoLabel = document.getElementById('total-so-label');
                    const totalSoValue = document.getElementById('total-so-value');
                    const pendingSoLabel = document.getElementById('pending-so-label');
                    const pendingSoValue = document.getElementById('pending-so-value');
                    const stockValueLabel = document.getElementById('stock-value-label');
                    const stockValueValue = document.getElementById('stock-value-value');
                    const storeReturnsLabel = document.getElementById('store-returns-label');
                    const storeReturnsValue = document.getElementById('store-returns-value');

                    const arPieTotal = document.getElementById('ar-pie-total');
                    const arPieChartCanvas = document.getElementById('ar-pie-chart');
                    let arPieChart;

                    // Initialize Flatpickr
                    const startDatePicker = flatpickr(startDateFilter, {
                        dateFormat: "d/m/Y",
                        defaultDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
                        onChange: function(selectedDates, dateStr, instance) {
                            endDatePicker.set('minDate', selectedDates[0]);
                            fetchSalesMetrics();
                        }
                    });

                    const endDatePicker = flatpickr(endDateFilter, {
                        dateFormat: "d/m/Y",
                        defaultDate: new Date(),
                        onChange: function(selectedDates, dateStr, instance) {
                            fetchSalesMetrics();
                        }
                    });

                    // Fetch locations
                    function fetchLocations() {
                        fetch('/locations')
                            .then(response => {
                                if (!response.ok) {
                                    return response.json().then(err => {
                                        throw err;
                                    });
                                }
                                return response.json();
                            })
                            .then(locations => {
                                if (locations.error) {
                                    throw locations;
                                }
                                locations.forEach(location => {
                                    const option = document.createElement('option');
                                    option.value = location;
                                    option.textContent = location;
                                    locationFilter.appendChild(option);
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching locations:', error.error || error);
                                const option = document.createElement('option');
                                option.textContent = 'Error loading locations';
                                locationFilter.appendChild(option);
                            });
                    }

                    // Fetch and update sales metrics data
                    function fetchSalesMetrics() {
                        const location = locationFilter.value;
                        const startDate = startDatePicker.selectedDates[0] ? startDatePicker.selectedDates[0].toISOString().split('T')[0] : '';
                        const endDate = endDatePicker.selectedDates[0] ? endDatePicker.selectedDates[0].toISOString().split('T')[0] : '';

                        const url = new URL(salesMetricsUrl);
                        url.searchParams.append('location', location);
                        url.searchParams.append('start_date', startDate);
                        // Show loading state
                        totalSoValue.textContent = 'Loading...';
                        pendingSoValue.textContent = 'Loading...';
                        stockValueValue.textContent = 'Loading...';
                        storeReturnsValue.textContent = 'Loading...';
                        arPieTotal.textContent = 'Loading...';

                        fetch(`/sales-metrics?location=${location}&start_date=${startDate}&end_date=${endDate}`)
                            .then(response => {
                                if (!response.ok) {
                                    return response.json().then(err => {
                                        throw err;
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.error) {
                                    throw data;
                                }
                                updateUI(data);
                            })
                            .catch(error => {
                                console.error('Error fetching sales metrics:', error.error || error);
                                totalSoValue.textContent = 'Error';
                                pendingSoValue.textContent = 'Error';
                                stockValueValue.textContent = 'Error';
                                storeReturnsValue.textContent = 'Error';
                                arPieTotal.textContent = 'Error';
                            });
                    }

                    function formatCurrency(value, precision = 2) {
                        if (value >= 1e9) {
                            return `Rp ${(value / 1e9).toFixed(precision)}B`;
                        } else if (value >= 1e6) {
                            return `Rp ${(value / 1e6).toFixed(precision)}M`;
                        } else if (value >= 1e3) {
                            return `Rp ${(value / 1e3).toFixed(2)}K`;
                        }
                        return `Rp ${value}`;
                    }

                    // Update UI with fetched data
                    function updateUI(data) {
                        // Update labels
                        totalSoLabel.textContent = `Total Sales Order ${data.date_range}`;
                        pendingSoLabel.textContent = `Pending Sales Order ${data.date_range}`;
                        stockValueLabel.textContent = `Stock Value ${data.date_range}`;
                        storeReturnsLabel.textContent = `Store Returns ${data.date_range}`;

                        // Update values
                        totalSoValue.textContent = formatCurrency(data.total_so, 2);
                        pendingSoValue.textContent = formatCurrency(data.pending_so, 2);
                        stockValueValue.textContent = formatCurrency(data.stock_value, 2);
                        storeReturnsValue.textContent = formatCurrency(data.store_returns, 2);

                        arPieTotal.textContent = formatCurrency(data.ar_pie_chart.total, 2);

                        // Update Pie Chart
                        if (arPieChart) {
                            arPieChart.destroy();
                        }
                        arPieChart = new Chart(arPieChartCanvas, {
                            type: 'pie',
                            data: {
                                labels: data.ar_pie_chart.labels,
                                datasets: [{
                                    data: data.ar_pie_chart.data,
                                    backgroundColor: [
                                        'rgba(22, 220, 160, 0.8)', // 1 - 30 Days
                                        'rgba(139, 92, 246, 0.8)', // 31 - 60 Days
                                        'rgba(251, 146, 60, 0.8)', // 61 - 90 Days
                                        'rgba(244, 63, 94, 0.8)' // > 90 Days
                                    ],
                                    borderColor: '#fff',
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                    },
                                    datalabels: {
                                        formatter: (value, ctx) => {
                                            let sum = 0;
                                            let dataArr = ctx.chart.data.datasets[0].data;
                                            dataArr.map(data => {
                                                sum += data;
                                            });
                                            if (sum === 0) return '0.00%';
                                            let percentage = (value * 100 / sum).toFixed(2) + "%";
                                            return percentage;
                                        },
                                        color: '#fff',
                                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                                        borderRadius: 4,
                                        font: {
                                            weight: 'bold'
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Add event listeners
                    locationFilter.addEventListener('change', fetchSalesMetrics);

                    // Initial load
                    fetchLocations();
                    fetchSalesMetrics();
                });
            </script>
            @endpush

        </div>
    </div>

</x-app-layout>
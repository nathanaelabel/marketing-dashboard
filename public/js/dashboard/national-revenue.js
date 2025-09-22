document.addEventListener('DOMContentLoaded', function () {
    let nationalRevenueChartInstance;
    const nationalTotalRevenueDisplay = document.getElementById('nationalTotalRevenueDisplay');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const revenueChartCanvas = document.getElementById('revenueChart');
    const messageContainer = document.getElementById('national-revenue-message');

    if (!revenueChartCanvas) return;

    function showNoDataMessage(message) {
        if (messageContainer) {
            messageContainer.innerHTML = `
                <div class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>${message}</span>
                </div>`;
            messageContainer.style.display = 'block';
        }
    }

    function clearMessages() {
        if (messageContainer) {
            messageContainer.innerHTML = '';
            messageContainer.style.display = 'none';
        }
    }

    function updateNationalRevenueDisplayAndChart(dataFromServer) {
        if (!dataFromServer) {
            nationalTotalRevenueDisplay.textContent = 'Error loading data';
            return;
        }

        nationalTotalRevenueDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(dataFromServer.totalRevenue);

        if (dataFromServer.totalRevenue === 0) {
            showNoDataMessage('No data available for the selected date range. Please try another date range.');
        } else {
            clearMessages();
        }

        const chartLabels = dataFromServer.labels;
        const chartDataValues = dataFromServer.datasets[0].data;

        const yAxisLabel = dataFromServer.yAxisLabel;
        const yAxisDivisor = dataFromServer.yAxisDivisor;
        const suggestedMax = dataFromServer.suggestedMax;

        // Use centralized formatting from ChartHelper
        const formatValueWithUnit = (value) => {
            return ChartHelper.formatValueWithUnit(value, yAxisDivisor, dataFromServer.yAxisUnit, 1);
        };

        if (nationalRevenueChartInstance) {
            nationalRevenueChartInstance.data.labels = chartLabels;
            nationalRevenueChartInstance.data.datasets[0].data = chartDataValues;
            nationalRevenueChartInstance.options.scales.y.title.text = yAxisLabel;
            nationalRevenueChartInstance.options.scales.y.suggestedMax = suggestedMax;
            nationalRevenueChartInstance.options.scales.y.ticks.callback = function (value) {
                const scaledValue = value / yAxisDivisor;
                if (dataFromServer.yAxisUnit === 'M') {
                    if (scaledValue % 1 === 0) return scaledValue.toFixed(0);
                    return scaledValue.toFixed(1);
                } else {
                    return Math.round(scaledValue);
                }
            };
            nationalRevenueChartInstance.options.plugins.datalabels.formatter = formatValueWithUnit;

            nationalRevenueChartInstance.update();
        } else {
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
                                callback: function (value) {
                                    const scaledValue = value / yAxisDivisor;
                                    if (dataFromServer.yAxisUnit === 'M') {
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
        clearMessages();
        const url = `${revenueChartCanvas.dataset.url}?start_date=${startDate}&end_date=${endDate}`;

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
                ChartHelper.showErrorMessage(nationalRevenueChartInstance, revenueChartCanvas, 'Failed to load chart data. Please try again.');
            });
    }

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
        onChange: function (selectedDates, dateStr, instance) {
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
        onChange: function (selectedDates, dateStr, instance) {
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
});

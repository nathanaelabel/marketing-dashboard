document.addEventListener('DOMContentLoaded', function () {
    let nationalRevenueChartInstance;
    const nationalTotalRevenueDisplay = document.getElementById('nationalTotalRevenueDisplay');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const typeSelect = document.getElementById('national-type-select');
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
        const type = typeSelect ? typeSelect.value : 'BRUTO';
        const url = `${revenueChartCanvas.dataset.url}?start_date=${startDate}&end_date=${endDate}&type=${type}`;

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

    // Listen for type selector changes
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            triggerUpdate();
        });
    }

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

    // Use yesterday (H-1) as max date since dashboard is updated daily
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    
    endDatePicker = flatpickr(endDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        maxDate: yesterday,
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

    // Three-dots menu toggle
    const menuButton = document.getElementById('menuButton');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (menuButton && dropdownMenu) {
        // Toggle dropdown on button click
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
    const refreshBtn = document.getElementById('refreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Get current date values and refresh the chart
            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType = typeSelect ? typeSelect.value : 'BRUTO';

            if (currentStartDate && currentEndDate) {
                fetchAndUpdateNationalRevenueChart(currentStartDate, currentEndDate);
            }
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('exportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType = typeSelect ? typeSelect.value : 'BRUTO';

            if (!currentStartDate || !currentEndDate) {
                alert('Please select both start and end dates');
                return;
            }

            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            const exportUrl = `/national-revenue/export-excel?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

            window.location.href = exportUrl;

            setTimeout(() => {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalContent;
            }, 2000);
        });
    }

    // Export to PDF functionality
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType = typeSelect ? typeSelect.value : 'BRUTO';

            if (!currentStartDate || !currentEndDate) {
                alert('Please select both start and end dates');
                return;
            }

            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            const exportPdfUrl = `/national-revenue/export-pdf?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

            window.location.href = exportPdfUrl;

            setTimeout(() => {
                exportPdfBtn.disabled = false;
                exportPdfBtn.innerHTML = originalContent;
            }, 2000);
        });
    }
});

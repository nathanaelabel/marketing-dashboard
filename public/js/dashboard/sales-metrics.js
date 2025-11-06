document.addEventListener('DOMContentLoaded', function () {
    const jsData = document.getElementById('js-data');
    if (!jsData) return;

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

    Chart.register(ChartDataLabels);

    // Initialize Flatpickr
    let startDatePicker, endDatePicker;

    // Use yesterday (H-1) as max date since dashboard is updated daily at night
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    // Set initial values before initializing flatpickr - use yesterday
    const yyyy = yesterday.getFullYear();
    const mm = String(yesterday.getMonth() + 1).padStart(2, '0');
    const dd = String(yesterday.getDate()).padStart(2, '0');
    endDateFilter.value = `${yyyy}-${mm}-${dd}`;

    endDatePicker = flatpickr(endDateFilter, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        defaultDate: yesterday,
        maxDate: yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (startDatePicker) {
                startDatePicker.set('maxDate', selectedDates[0]);
            }
            fetchSalesMetrics('date');
        }
    });

    startDatePicker = flatpickr(startDateFilter, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        defaultDate: firstDayOfMonth,
        maxDate: endDatePicker.selectedDates[0] || yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (endDatePicker) {
                endDatePicker.set('minDate', selectedDates[0]);
            }
            fetchSalesMetrics('date');
        }
    });

    // Fetch locations
    function fetchLocations() {
        fetch('/sales-metrics/locations')
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw err;
                    });
                }
                return response.json();
            })
            .then(locationOptions => {
                if (locationOptions.error) {
                    throw locationOptions;
                }

                // Clear existing options first
                locationFilter.innerHTML = '';

                locationOptions.forEach(locationOption => {
                    const option = document.createElement('option');
                    option.value = locationOption.value;
                    option.textContent = locationOption.display;
                    locationFilter.appendChild(option);
                });

                // Set default to National (%) if available
                const nationalOption = locationOptions.find(option => option.value === '%');
                if (nationalOption) {
                    locationFilter.value = '%';
                }
            })
            .catch(error => {
                console.error('Error fetching locations:', error.error || error);
                const option = document.createElement('option');
                option.textContent = 'Error loading locations';
                locationFilter.appendChild(option);
            });
    }

    // Fetch and update sales metrics data
    function fetchSalesMetrics(source = 'initial') {
        const location = locationFilter.value;
        const startDate = startDateFilter.value;
        const endDate = endDateFilter.value;

        const url = new URL(salesMetricsUrl);
        url.searchParams.append('location', location);
        url.searchParams.append('start_date', startDate);
        url.searchParams.append('end_date', endDate);

        // Show loading state
        totalSoValue.textContent = 'Loading...';
        pendingSoValue.textContent = 'Loading...';
        stockValueValue.textContent = 'Loading...';
        storeReturnsValue.textContent = 'Loading...';

        if (source === 'location' || source === 'initial') {
            arPieTotal.textContent = 'Loading...';
        }

        fetch(url)
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
                updateUI(data, source);
            })
            .catch(error => {
                console.error('Error fetching sales metrics:', error.error || error);
                totalSoValue.textContent = 'Error';
                pendingSoValue.textContent = 'Error';
                stockValueValue.textContent = 'Error';
                storeReturnsValue.textContent = 'Error';
                arPieTotal.textContent = 'Error';

                // Also display a proper error message in the chart area
                ChartHelper.showErrorMessage(arPieChart, arPieChartCanvas, 'Failed to load chart data. Please try again.');
            });
    }

    // Use centralized formatting from ChartHelper
    function formatCurrency(value, precision = 2) {
        return ChartHelper.formatCurrencyDisplay(value, precision, true);
    }

    function updateUI(data, source) {
        // Extract end date from date range for Stock Value (point-in-time metric)
        const endDate = data.date_range.split(' - ')[1] || data.date_range;

        // Update labels and values for metric cards
        totalSoLabel.textContent = `Total Sales Order ${data.date_range}`;
        pendingSoLabel.textContent = `Pending Sales Order ${data.date_range}`;
        storeReturnsLabel.textContent = `Store Returns ${data.date_range}`;
        stockValueLabel.textContent = `Stock Value ${endDate}`;

        totalSoValue.textContent = formatCurrency(data.total_so, 2);
        pendingSoValue.textContent = formatCurrency(data.pending_so, 2);
        stockValueValue.textContent = formatCurrency(data.stock_value, 2);
        storeReturnsValue.textContent = formatCurrency(data.store_returns, 2);

        // Update AR Pie Chart only if location changed or initial load
        if (source === 'location' || source === 'initial') {
            updateArPieChart(data.ar_pie_chart);
        }
    }

    function updateArPieChart(data) {
        arPieTotal.textContent = formatCurrency(data.total, 2);

        if (arPieChart) {
            arPieChart.destroy();
        }

        arPieChart = new Chart(arPieChartCanvas, {
            type: 'pie',
            data: {
                labels: ['1 - 30 Days', '31 - 60 Days', '61 - 90 Days', '> 90 Days'],
                datasets: [{
                    data: data.data,
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
                            let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
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
    locationFilter.addEventListener('change', () => fetchSalesMetrics('location'));

    // Initial load
    fetchLocations();
    fetchSalesMetrics('initial');
});

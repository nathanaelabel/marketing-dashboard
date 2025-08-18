document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('categoryItemChart');
    if (!chartCanvas) return;

    let chartInstance;
    let currentPage = 1;

    const prevButton = document.getElementById('ci-prev-page');
    const nextButton = document.getElementById('ci-next-page');

    const formatCurrency = (value) => {
        if (Math.abs(value) >= 1e9) {
            return 'Rp ' + (value / 1e9).toFixed(1) + 'B';
        } else if (Math.abs(value) >= 1e6) {
            return 'Rp ' + (value / 1e6).toFixed(1) + 'M';
        } else if (Math.abs(value) >= 1e3) {
            return 'Rp ' + (value / 1e3).toFixed(1) + 'K';
        }
        return 'Rp ' + value;
    };

    async function fetchAndUpdateChart(page = 1) {
        currentPage = page;
        const startDate = document.getElementById('ci_start_date').value;
        const endDate = document.getElementById('ci_end_date').value;
        const url = new URL(chartCanvas.dataset.url);
        url.searchParams.append('start_date', startDate);
        url.searchParams.append('end_date', endDate);
        url.searchParams.append('page', page);

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok.');
            const data = await response.json();

            if (chartInstance) {
                chartInstance.destroy();
            }

            const chartConfig = {
                type: 'treemap',
                data: {
                    datasets: data.datasets.map(ds => ({
                        ...ds,
                        key: 'y',
                        groups: ['x'],
                        borderWidth: 1,
                        borderColor: 'white'
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false,
                        },
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const datasetLabel = context.dataset.label || '';
                                    const value = context.raw.y;
                                    const total = context.raw.v;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${datasetLabel}: ${formatCurrency(value)} (${percentage}%)`;
                                }
                            }
                        },
                        datalabels: {
                            display: true,
                            color: 'white',
                            font: {
                                weight: 'bold'
                            },
                            formatter: (value, context) => {
                                const total = context.dataset.tree[context.dataIndex].v;
                                const percentage = total > 0 ? (value.y / total * 100) : 0;
                                if (percentage < 5) return '';
                                return `${percentage.toFixed(0)}%`;
                            }
                        }
                    },
                }
            };

            chartInstance = new Chart(chartCanvas, chartConfig);

            // Update pagination buttons state
            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = !data.pagination.hasMorePages;

        } catch (error) {
            console.error('Error fetching or rendering chart:', error);
            const ctx = chartCanvas.getContext('2d');
            ctx.clearRect(0, 0, chartCanvas.width, chartCanvas.height);
            ctx.textAlign = 'center';
            ctx.fillText('Error loading chart data.', chartCanvas.width / 2, chartCanvas.height / 2);
        }
    }

    prevButton.addEventListener('click', () => {
        if (currentPage > 1) {
            fetchAndUpdateChart(currentPage - 1);
        }
    });

    nextButton.addEventListener('click', () => {
        fetchAndUpdateChart(currentPage + 1);
    });

    let startDatePicker, endDatePicker;

    const triggerUpdate = () => {
        fetchAndUpdateChart(1); // Always fetch page 1 when date changes
    };

    const startDateInput = document.getElementById('categoryItemStartDate');
    const endDateInput = document.getElementById('categoryItemEndDate');

    startDatePicker = flatpickr(startDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        maxDate: endDateInput.value || "today",
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
        minDate: startDateInput.value,
        maxDate: "today",
        onChange: function (selectedDates, dateStr, instance) {
            if (startDatePicker) {
                startDatePicker.set('maxDate', selectedDates[0]);
            }
            triggerUpdate();
        }
    });

    fetchAndUpdateChart(1);
});

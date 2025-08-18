document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('categoryItemChart');
    if (!chartCanvas) return;

    let chartInstance;

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

    async function fetchAndUpdateChart() {
        const startDate = document.getElementById('ci_start_date').value;
        const endDate = document.getElementById('ci_end_date').value;
        const url = new URL(chartCanvas.dataset.url);
        url.searchParams.append('start_date', startDate);
        url.searchParams.append('end_date', endDate);

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok.');
            const data = await response.json();

            if (chartInstance) {
                chartInstance.destroy();
            }

            const flatData = data.datasets.flatMap(ds =>
                ds.data.map(d => ({ ...d, category: ds.label }))
            );

            const chartConfig = {
                type: 'treemap',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
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
                                const total = context.dataset.data[context.dataIndex].v;
                                const percentage = total > 0 ? (value.y / total * 100) : 0;
                                if (percentage < 5) return '';
                                return `${formatCurrency(value.y)} (${percentage.toFixed(0)}%)`;
                            }
                        }
                    },
                }
            };

            chartInstance = new Chart(chartCanvas, chartConfig);

        } catch (error) {
            console.error('Error fetching or rendering chart:', error);
            const ctx = chartCanvas.getContext('2d');
            ctx.clearRect(0, 0, chartCanvas.width, chartCanvas.height);
            ctx.textAlign = 'center';
            ctx.fillText('Error loading chart data.', chartCanvas.width / 2, chartCanvas.height / 2);
        }
    }

    const startDatePicker = flatpickr("#ci_start_date", {
        defaultDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        onChange: function (selectedDates, dateStr, instance) {
            endDatePicker.set('minDate', selectedDates[0]);
            fetchAndUpdateChart();
        }
    });

    const endDatePicker = flatpickr("#ci_end_date", {
        defaultDate: new Date(),
        onChange: function (selectedDates, dateStr, instance) {
            fetchAndUpdateChart();
        }
    });

    fetchAndUpdateChart();
});

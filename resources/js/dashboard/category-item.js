import { Chart, registerables } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(...registerables, ChartDataLabels);

document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('categoryItemChart');
    if (!ctx) return;

    let chart;
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
        const startDate = document.getElementById('categoryItemStartDate').value;
        const endDate = document.getElementById('categoryItemEndDate').value;
        const url = new URL(ctx.dataset.url);
        url.searchParams.append('start_date', startDate);
        url.searchParams.append('end_date', endDate);
        url.searchParams.append('page', page);

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok.');
            const data = await response.json();

            if (chart) {
                chart.destroy();
            }

            data.chartData.datasets.forEach(ds => {
                ds.barPercentage = 1.0;
                ds.categoryPercentage = 1.0;
            });

            chart = new Chart(ctx, {
                type: 'bar',
                data: data.chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            ticks: {
                                autoSkip: false,
                                maxRotation: 90,
                                minRotation: 45
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            max: 1,
                            ticks: {
                                callback: function (value) {
                                    return (value * 100) + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function (context) {
                                    return context[0].label;
                                },
                                label: function (context) {
                                    const raw = context.raw;
                                    const total = raw.value;
                                    const percentage = total > 0 ? ((raw.v / total) * 100).toFixed(2) : 0;
                                    return `${context.dataset.label}: ${formatCurrency(raw.v)} (${percentage}%)`;
                                }
                            }
                        },
                        legend: {
                            position: 'bottom',
                        },
                        datalabels: {
                            color: '#fff',
                            anchor: 'center',
                            align: 'center',
                            formatter: function (value, context) {
                                return formatCurrency(context.raw.v);
                            },
                            font: {
                                weight: 'bold'
                            },
                            display: function(context) {
                                return context.raw.y > 0.05; // Only show label if segment is large enough
                            }
                        }
                    }
                }
            });

            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = !data.pagination.hasMorePages;

        } catch (error) {
            console.error('Error fetching or rendering chart:', error);
            ctx.parentElement.innerHTML = '<div style="text-align: center; padding-top: 50px;">Error loading chart data.</div>';
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
        fetchAndUpdateChart(1);
    };

    const startDateInput = document.getElementById('categoryItemStartDate');
    const endDateInput = document.getElementById('categoryItemEndDate');

    startDatePicker = flatpickr(startDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        defaultDate: new Date().setDate(1),
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
        defaultDate: "today",
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

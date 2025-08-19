document.addEventListener('DOMContentLoaded', function () {
    const arChartCanvas = document.getElementById('accountsReceivableChart');
    let arChart;

    if (!arChartCanvas) return;

    Chart.register(ChartDataLabels);

    function fetchAndUpdateAccountsReceivableChart() {
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
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function (context) {
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
                                formatter: function (value, context) {
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
                                    callback: function (value) {
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

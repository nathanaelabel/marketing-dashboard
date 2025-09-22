document.addEventListener('DOMContentLoaded', function () {
    const arChartCanvas = document.getElementById('accountsReceivableChart');
    let arChart;

    if (!arChartCanvas) return;

    // Custom plugin to add extra margin under the legend
    const LegendMargin = {
        id: 'legendMargin',
        beforeInit(chart, _args, opts) {
            const fit = chart.legend && chart.legend.fit;
            if (!fit) return;
            chart.legend.fit = function fitWithMargin() {
                fit.bind(this)();
                this.height += (opts && opts.margin) ? opts.margin : 0;
            };
        }
    };

    Chart.register(ChartDataLabels, LegendMargin);

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
                                position: 'top',
                                labels: {
                                    padding: 12
                                }
                            },
                            // extra space under legend
                            legendMargin: {
                                margin: 10
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
                                            // Format with Indonesian number format using dots as thousand separators
                                            const formattedValue = Math.round(context.parsed.y).toLocaleString('id-ID');
                                            label += formattedValue;
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
                                    if (value < 1800000000) { // Hide labels for values less than 1.5B
                                        return null;
                                    }
                                    const billions = value / 1000000000;
                                    const display = Math.round(billions * 10) / 10;
                                    if (display % 1 === 0) {
                                        return display.toFixed(0) + 'M';
                                    }
                                    return display.toFixed(1) + 'M';
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
                                        // Show clean numbers like 230, 200, 150 (value is in billions)
                                        const scaledValue = value / 1000000000;
                                        return Math.round(scaledValue);
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Miliar Rupiah'
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching Accounts Receivable data:', error);
                if (arTotalEl) arTotalEl.textContent = 'Error loading data.';
                ChartHelper.showErrorMessage(arChart, arChartCanvas, 'Failed to load chart data. Please try again.');
            });
    }

    fetchAndUpdateAccountsReceivableChart();
});

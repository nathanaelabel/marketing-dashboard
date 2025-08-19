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

            console.log('Received data:', data);

            if (chart) {
                chart.destroy();
            }

            // Check if we have valid chart data
            if (!data.chartData || !data.chartData.labels || !data.chartData.datasets) {
                ctx.parentElement.innerHTML = '<div style="text-align: center; padding-top: 50px; color: #6b7280;">No data available for the selected date range.</div>';
                prevButton.disabled = currentPage <= 1;
                nextButton.disabled = !data.pagination.hasMorePages;
                return;
            }

            // Transform data for stacked bar chart with normalization
            const transformedData = {
                labels: data.chartData.labels,
                datasets: data.chartData.datasets.map(dataset => ({
                    ...dataset,
                    data: dataset.data.map(point => point.y || 0),
                    originalData: dataset.data // Keep original data for tooltips
                }))
            };

            // Function to normalize data when datasets are toggled
            const normalizeData = () => {
                const visibleDatasets = transformedData.datasets.filter((dataset, index) =>
                    !chart.isDatasetVisible || chart.isDatasetVisible(index)
                );

                transformedData.labels.forEach((label, branchIndex) => {
                    const visibleTotal = visibleDatasets.reduce((sum, dataset) => {
                        return sum + (dataset.originalData[branchIndex]?.y || 0);
                    }, 0);

                    if (visibleTotal > 0) {
                        visibleDatasets.forEach(dataset => {
                            const originalValue = dataset.originalData[branchIndex]?.y || 0;
                            dataset.data[branchIndex] = originalValue / visibleTotal;
                        });
                    }
                });
            };

            chart = new Chart(ctx, {
                type: 'bar',
                data: transformedData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            bottom: 10 // extra space between legend and page button
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            },
                            ticks: {
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            max: 1,
                            grid: {
                                display: false
                            },
                            ticks: {
                                callback: function (value) {
                                    return (value * 100) + '%';
                                }
                            }
                        }
                    },
                    categoryPercentage: 1.0,
                    barPercentage: 1.0,
                    datasets: {
                        bar: {
                            borderWidth: 1,
                            borderColor: 'white'
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function (context) {
                                    return context[0].label;
                                },
                                label: function (context) {
                                    const originalData = transformedData.datasets[context.datasetIndex].originalData[context.dataIndex];
                                    if (!originalData || !originalData.v) return '';
                                    const total = originalData.value;
                                    const percentage = total > 0 ? ((originalData.v / total) * 100).toFixed(2) : 0;
                                    return `${context.dataset.label}: ${formatCurrency(originalData.v)} (${percentage}%)`;
                                }
                            }
                        },
                        legend: {
                            position: 'bottom',
                            onClick: function (e, legendItem, legend) {
                                const index = legendItem.datasetIndex;
                                const chart = legend.chart;

                                if (chart.isDatasetVisible(index)) {
                                    chart.hide(index);
                                } else {
                                    chart.show(index);
                                }

                                // Normalize data after toggling
                                const visibleDatasets = transformedData.datasets.filter((dataset, idx) =>
                                    chart.isDatasetVisible(idx)
                                );

                                transformedData.labels.forEach((label, branchIndex) => {
                                    const visibleTotal = visibleDatasets.reduce((sum, dataset) => {
                                        return sum + (dataset.originalData[branchIndex]?.y || 0);
                                    }, 0);

                                    if (visibleTotal > 0) {
                                        transformedData.datasets.forEach((dataset, idx) => {
                                            if (chart.isDatasetVisible(idx)) {
                                                const originalValue = dataset.originalData[branchIndex]?.y || 0;
                                                dataset.data[branchIndex] = originalValue / visibleTotal;
                                            }
                                        });
                                    }
                                });

                                chart.update();
                            }
                        },
                        datalabels: {
                            color: '#333',
                            anchor: 'center',
                            align: 'center',
                            formatter: function (value, context) {
                                const originalData = transformedData.datasets[context.datasetIndex].originalData[context.dataIndex];
                                return originalData && originalData.v && originalData.v > 0 ? formatCurrency(originalData.v) : '';
                            },
                            font: {
                                weight: 'bold',
                                size: 10
                            },
                            display: function (context) {
                                const chart = context.chart;
                                const originalData = transformedData.datasets[context.datasetIndex].originalData[context.dataIndex];
                                const value = originalData ? originalData.v : 0;

                                if (!value || value <= 0) {
                                    return false;
                                }

                                const categoryNames = chart.data.datasets.map(d => d.label);
                                const mikaIdx = categoryNames.indexOf('MIKA');
                                const sparePartIdx = categoryNames.indexOf('SPARE PART');
                                const catIdx = categoryNames.indexOf('CAT');
                                const aksesorisIdx = categoryNames.indexOf('AKSESORIS');
                                const productImportIdx = categoryNames.indexOf('PRODUCT IMPORT');

                                const isVisible = (idx) => idx !== -1 && chart.isDatasetVisible(idx);

                                const isMikaVis = isVisible(mikaIdx);
                                const isSparePartVis = isVisible(sparePartIdx);
                                const isCatVis = isVisible(catIdx);
                                const isAksesorisVis = isVisible(aksesorisIdx);
                                const isProductImportVis = isVisible(productImportIdx);

                                const visibleCount = [isMikaVis, isSparePartVis, isCatVis, isAksesorisVis, isProductImportVis].filter(Boolean).length;

                                // When 4 categories are hidden (1 is visible), show all labels.
                                if (visibleCount <= 1) {
                                    return true;
                                }

                                // When Mika, Spare Part, and Cat are hidden
                                if (!isMikaVis && !isSparePartVis && !isCatVis) {
                                    return value > 1000;
                                }

                                // When Mika, Spare Part, and Aksesoris are hidden
                                if (!isMikaVis && !isSparePartVis && !isAksesorisVis) {
                                    return value > 90000;
                                }

                                // When Mika, Spare Part, and Product Import are hidden
                                if (!isMikaVis && !isSparePartVis && !isProductImportVis) {
                                    return value > 30000;
                                }

                                // When Mika and Spare Part are hidden
                                if (!isMikaVis && !isSparePartVis) {
                                    return value > 300000;
                                }

                                // When only Mika is hidden
                                if (!isMikaVis) {
                                    return value > 6000000;
                                }

                                // Default behavior: hide labels under 55M
                                return value > 55000000;
                            }
                        }
                    },
                    onHover: (event, activeElements) => {
                        event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
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

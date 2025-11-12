import { Chart, registerables } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';

// Custom plugin to add extra margin under the legend
const LegendMargin = {
    id: 'legendMargin',
    beforeInit(chart, _args, opts) {
        const fit = chart.legend && chart.legend.fit;
        if (!fit) return;
        chart.legend.fit = function fitWithMargin() {
            fit.bind(this)();
            // increase legend height by configured margin
            this.height += (opts && opts.margin) ? opts.margin : 0;
        };
    }
};

Chart.register(...registerables, ChartDataLabels, LegendMargin);

document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('categoryItemChart');
    if (!ctx) return;

    const messageContainer = document.getElementById('category-item-message');

    function showNoDataMessage(message) {
        if (messageContainer) {
            messageContainer.innerHTML = `
                <div class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>${message}</span>
                </div>`;
            messageContainer.style.display = 'flex';
        }
    }

    function clearMessages() {
        if (messageContainer) {
            messageContainer.innerHTML = '';
            messageContainer.style.display = 'none';
        }
    }

    let chart;
    let currentPage = 1;

    const prevButton = document.getElementById('ci-prev-page');
    const nextButton = document.getElementById('ci-next-page');

    // Use centralized formatting from ChartHelper
    const formatCurrency = (value) => {
        return ChartHelper.formatCurrencyDisplay(value, 1, true);
    };

    async function fetchAndUpdateChart(page = 1) {
        currentPage = page;
        clearMessages();
        const startDate = document.getElementById('categoryItemStartDate').value;
        const endDate = document.getElementById('categoryItemEndDate').value;
        const type = document.getElementById('categoryItemTypeSelect').value;
        const url = new URL(ctx.dataset.url);
        url.searchParams.append('start_date', startDate);
        url.searchParams.append('end_date', endDate);
        url.searchParams.append('type', type);
        url.searchParams.append('page', page);

        // Get filter selectors for disabling during load
        const filterSelectors = ['categoryItemTypeSelect', 'categoryItemStartDate', 'categoryItemEndDate', 'ci-prev-page', 'ci-next-page'];
        const chartContainer = document.getElementById('category-item-chart-container');

        // Disable filters and show loading on chart area only
        ChartHelper.disableFilters(filterSelectors);
        ChartHelper.showChartLoadingIndicator(chartContainer);

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok.');
            const data = await response.json();

            ChartHelper.hideChartLoadingIndicator(chartContainer);
            ChartHelper.enableFilters(filterSelectors);

            console.log('Received data:', data);

            if (chart) {
                chart.destroy();
                chart = null;
            }

            // Check if we have valid chart data
            if (!data.chartData || !data.chartData.labels || data.chartData.labels.length === 0 || !data.chartData.datasets || data.chartData.datasets.length === 0) {
                ChartHelper.hideChartLoadingIndicator(chartContainer);
                ChartHelper.enableFilters(filterSelectors);
                showNoDataMessage('No data available for the selected date range. Please try another date range.');
                prevButton.disabled = currentPage <= 1;
                nextButton.disabled = !data.pagination.hasMorePages;
                return;
            }

            // Transform data for stacked bar chart with normalization
            const transformedData = {
                labels: data.chartData.labels,
                datasets: data.chartData.datasets.map(dataset => ({
                    ...dataset,
                    data: dataset.data.map(point => Math.max(0, point.y || 0)),
                    originalData: dataset.data.map(point => ({
                        ...point,
                        y: Math.max(0, point.y || 0), // Ensure original data also has no negative percentages
                        v: Math.max(0, point.v || 0) // Ensure revenue values are never negative
                    }))
                }))
            };

            // Function to normalize data when datasets are toggled
            const normalizeData = () => {
                const visibleDatasets = transformedData.datasets.filter((dataset, index) =>
                    !chart.isDatasetVisible || chart.isDatasetVisible(index)
                );

                transformedData.labels.forEach((label, branchIndex) => {
                    const visibleTotal = visibleDatasets.reduce((sum, dataset) => {
                        const value = dataset.originalData[branchIndex]?.y || 0;
                        return sum + Math.max(0, value);
                    }, 0);

                    if (visibleTotal > 0) {
                        visibleDatasets.forEach(dataset => {
                            const originalValue = dataset.originalData[branchIndex]?.y || 0;
                            const normalizedValue = Math.max(0, originalValue) / visibleTotal;
                            dataset.data[branchIndex] = Math.max(0, normalizedValue); // Ensure percentage is never negative
                        });
                    } else {
                        // If total is 0 or negative, set all to 0
                        visibleDatasets.forEach(dataset => {
                            dataset.data[branchIndex] = 0;
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
                            bottom: 10 // space between chart and page buttons
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
                            min: 0,
                            max: 1,
                            grid: {
                                display: false
                            },
                            ticks: {
                                callback: function (value) {
                                    return (value * 100) + '%';
                                },
                                stepSize: 0.2
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
                                    if (!originalData || !originalData.v || originalData.v <= 0) return '';
                                    const total = Math.max(0, originalData.value || 0);
                                    const revenue = Math.max(0, originalData.v || 0);
                                    const percentage = total > 0 ? Math.max(0, ((revenue / total) * 100)).toFixed(2) : 0;
                                    // Show full value with Indonesian number formatting
                                    const fullValue = Math.round(revenue).toLocaleString('id-ID');
                                    return `${context.dataset.label}: ${fullValue} (${percentage}%)`;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 12 // spacing between legend items
                            },
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
                                        const value = dataset.originalData[branchIndex]?.y || 0;
                                        return sum + Math.max(0, value); // Ensure no negative values
                                    }, 0);

                                    if (visibleTotal > 0) {
                                        transformedData.datasets.forEach((dataset, idx) => {
                                            if (chart.isDatasetVisible(idx)) {
                                                const originalValue = dataset.originalData[branchIndex]?.y || 0;
                                                const normalizedValue = Math.max(0, originalValue) / visibleTotal;
                                                dataset.data[branchIndex] = Math.max(0, normalizedValue); // Ensure percentage is never negative
                                            }
                                        });
                                    } else {
                                        // If total is 0 or negative, set all visible to 0
                                        transformedData.datasets.forEach((dataset, idx) => {
                                            if (chart.isDatasetVisible(idx)) {
                                                dataset.data[branchIndex] = 0;
                                            }
                                        });
                                    }
                                });

                                chart.update();
                            }
                        },
                        // apply extra space under legend (in addition to labels padding)
                        legendMargin: {
                            margin: 10
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
                                size: 12
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
                                return value > 60000000;
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
            ChartHelper.hideChartLoadingIndicator(chartContainer);
            ChartHelper.enableFilters(filterSelectors);
            ChartHelper.showErrorMessage(chart, ctx, 'Failed to load chart data. Please try again.');
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
    const typeSelect = document.getElementById('categoryItemTypeSelect');

    // Add change listener for type selector
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            triggerUpdate();
        });
    }

    // Use yesterday (H-1) as max date since dashboard is updated daily at night
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    startDatePicker = flatpickr(startDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        defaultDate: new Date().setDate(1),
        maxDate: endDateInput.value || yesterday,
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
        defaultDate: yesterday,
        minDate: startDateInput.value,
        maxDate: yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (startDatePicker) {
                startDatePicker.set('maxDate', selectedDates[0]);
            }
            triggerUpdate();
        }
    });

    fetchAndUpdateChart(1);

    // Three-dots menu toggle
    const menuButton = document.getElementById('ciMenuButton');
    const dropdownMenu = document.getElementById('ciDropdownMenu');

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
    const refreshBtn = document.getElementById('ciRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Refresh the chart (reset to page 1)
            fetchAndUpdateChart(1);
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('ciExportExcelBtn');
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

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportUrl = `/category-item/export-excel?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

            // Use window.location for direct download
            window.location.href = exportUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalContent;
            }, 2000);
        });
    }

    // Export to PDF functionality
    const exportPdfBtn = document.getElementById('ciExportPdfBtn');
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

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportPdfUrl = `/category-item/export-pdf?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

            // Use window.location for direct download
            window.location.href = exportPdfUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                exportPdfBtn.disabled = false;
                exportPdfBtn.innerHTML = originalContent;
            }, 2000);
        });
    }
});

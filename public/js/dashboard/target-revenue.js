document.addEventListener("DOMContentLoaded", function () {
    let targetRevenueChart = null;
    const ctx = document.getElementById("target-revenue-chart");
    const viewSelect = document.getElementById("target-view-select");
    const monthSelect = document.getElementById("target-month-select");
    const yearSelect = document.getElementById("target-year-select");
    const categorySelect = document.getElementById("target-category-select");
    const noTargetsMessage = document.getElementById("no-targets-message");
    const inputTargetBtn = document.getElementById("input-target-btn");
    const editTargetBtn = document.getElementById("edit-target-btn");
    const periodText = document.getElementById("period-text");
    const periodInfo = document.getElementById("target-period-info");
    const chartContainer = document.getElementById("target-chart-container");

    if (!ctx) return;

    function updateTargetRevenueChart(dataFromServer) {
        if (!dataFromServer) {
            console.error("No data received from server");
            return;
        }

        const chartLabels = dataFromServer.labels;
        const yAxisLabel = dataFromServer.yAxisLabel;
        const yAxisDivisor = dataFromServer.yAxisDivisor;
        const suggestedMax = dataFromServer.suggestedMax;
        const percentages = dataFromServer.percentages;

        // Update period info for 4-month view
        if (dataFromServer.view === "4-month" && dataFromServer.period_info) {
            // Seluruh teks "Period: ..." biru, hanya kata Period yang bold
            let periodHtml =
                '<span style="color: #2563EB;"><span style="font-weight: 600;">Period:</span> ' +
                dataFromServer.period_info +
                "</span>";

            // Tambahkan info bulan yang belum ada target (semua merah, label bold)
            if (
                dataFromServer.months_without_target &&
                dataFromServer.months_without_target.length > 0
            ) {
                periodHtml +=
                    '<br><span style="color: #DC2626; font-size: 0.875rem;"><span style="font-weight: 600;">Belum ada target:</span> ' +
                    dataFromServer.months_without_target.join(", ") +
                    "</span>";
            }

            periodInfo.innerHTML = periodHtml;
            periodInfo.classList.remove("hidden");
        } else {
            periodInfo.classList.add("hidden");
        }

        if (targetRevenueChart) {
            targetRevenueChart.destroy();
            targetRevenueChart = null;
        }

        // Register custom legend margin plugin
        const LegendMargin = {
            id: "legendMargin",
            beforeInit(chart, _args, opts) {
                const fit = chart.legend && chart.legend.fit;
                if (!fit) return;
                chart.legend.fit = function fitWithMargin() {
                    fit.bind(this)();
                    this.height += opts && opts.margin ? opts.margin : 0;
                };
            },
        };

        Chart.register(LegendMargin, ChartDataLabels);
        targetRevenueChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: chartLabels,
                datasets: dataFromServer.datasets,
            },
            options: {
                indexAxis: "y", // Horizontal bar chart
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: false,
                    },
                    legend: {
                        display: true,
                        position: "top",
                        labels: {
                            padding: 12,
                        },
                    },
                    legendMargin: {
                        margin: 10,
                    },
                    datalabels: {
                        display: function (context) {
                            return context.dataset.data[context.dataIndex] > 0;
                        },
                        anchor: "end",
                        align: function (context) {
                            return context.dataset.label === "Realization"
                                ? "right"
                                : "left";
                        },
                        offset: function (context) {
                            return context.dataset.label === "Realization"
                                ? 4
                                : -4;
                        },
                        color: function (context) {
                            return context.dataset.label === "Realization"
                                ? "#000"
                                : "#666";
                        },
                        font: {
                            size: 10,
                            weight: "bold",
                        },
                        formatter: function (value, context) {
                            if (
                                context.dataset.label === "Realization" &&
                                percentages[context.dataIndex] !== undefined
                            ) {
                                // Show both billion format and percentage for realization bars
                                const billionValue =
                                    ChartHelper.formatCurrencyDisplay(
                                        value,
                                        2,
                                        false
                                    );
                                return (
                                    billionValue +
                                    " (" +
                                    percentages[context.dataIndex] +
                                    "%)"
                                );
                            }
                            return ChartHelper.formatNumberForDisplay(
                                value,
                                yAxisDivisor
                            );
                        },
                    },
                    tooltip: {
                        mode: "index",
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || "";
                                if (label) {
                                    label += ": ";
                                }
                                if (context.parsed.x !== null) {
                                    label += new Intl.NumberFormat("id-ID", {
                                        minimumFractionDigits: 0,
                                    }).format(context.parsed.x);

                                    if (
                                        context.dataset.label ===
                                            "Realization" &&
                                        percentages[context.dataIndex] !==
                                            undefined
                                    ) {
                                        label += ` (${
                                            percentages[context.dataIndex]
                                        }%)`;
                                    }
                                }
                                return label;
                            },
                        },
                    },
                },
                interaction: {
                    mode: "nearest",
                    axis: "y",
                    intersect: false,
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: suggestedMax,
                        title: {
                            display: true,
                            text: yAxisLabel,
                            padding: {
                                top: 20,
                                left: 0,
                                right: 0,
                                bottom: 0,
                            },
                        },
                        ticks: {
                            callback: function (value) {
                                const scaledValue = value / yAxisDivisor;
                                if (dataFromServer.yAxisUnit === "B") {
                                    if (scaledValue % 1 === 0)
                                        return scaledValue.toFixed(0);
                                    return scaledValue.toFixed(1);
                                } else {
                                    return Math.round(scaledValue);
                                }
                            },
                        },
                    },
                    y: {
                        title: {
                            display: false,
                        },
                    },
                },
                elements: {
                    bar: {
                        borderWidth: 1,
                    },
                },
            },
        });
    }

    function showNoTargetsMessage(data) {
        // Hide chart
        ctx.style.display = "none";

        // Show no targets message
        noTargetsMessage.classList.remove("hidden");

        // Hide edit button
        editTargetBtn.classList.add("hidden");

        // Update period text
        const months = [
            "",
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December",
        ];

        if (data.view === "4-month" && data.months_without_target) {
            // For 4-month view, show which months don't have targets
            const formattedCategory = data.category
                ? data.category
                      .toLowerCase()
                      .replace(/\b\w/g, (l) => l.toUpperCase())
                : "";
            periodText.innerHTML = `Belum ada target untuk ${data.months_without_target.join(
                ", "
            )}${
                formattedCategory ? " - " + formattedCategory : ""
            }.<br><span style="font-size: 0.875rem; color: #6B7280;">Silakan ganti ke 1-month view untuk input targetnya.</span>`;

            // Hide Input Target button for 4-month view
            if (inputTargetBtn) {
                inputTargetBtn.style.display = "none";
            }
        } else {
            const monthName = months[data.month] || "Unknown";
            const formattedCategory = data.category
                .toLowerCase()
                .replace(/\b\w/g, (l) => l.toUpperCase());
            periodText.textContent = `${monthName} ${data.year} - ${formattedCategory}`;

            // Show Input Target button for 1-month view
            if (inputTargetBtn) {
                inputTargetBtn.style.display = "";
            }
        }
    }

    function hideNoTargetsMessage() {
        // Show chart
        ctx.style.display = "block";

        // Hide no targets message
        noTargetsMessage.classList.add("hidden");

        // Show edit button
        editTargetBtn.classList.remove("hidden");

        // Reset Input Target button visibility
        if (inputTargetBtn) {
            inputTargetBtn.style.display = "";
        }

        // Clear period info when showing chart (switching from no-target to has-target)
        if (periodInfo) {
            periodInfo.classList.add("hidden");
            periodInfo.innerHTML = "";
        }
    }

    function clearMessages() {
        const errorMessage = chartContainer.querySelector(".error-message");
        const noDataMessage = chartContainer.querySelector(".no-data-message");

        if (errorMessage) errorMessage.remove();
        if (noDataMessage) noDataMessage.remove();
    }

    function fetchAndUpdateTargetChart(month, year, category, view) {
        const url = `/target-revenue/data?month=${month}&year=${year}&category=${encodeURIComponent(
            category
        )}&view=${view}`;
        const filterSelectors = [
            "target-view-select",
            "target-month-select",
            "target-year-select",
            "target-category-select",
        ];

        // Disable filters and edit button, show loading on chart area only
        ChartHelper.disableFilters(filterSelectors);
        if (editTargetBtn) editTargetBtn.disabled = true;
        ChartHelper.showChartLoadingIndicator(chartContainer);

        fetch(url)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                ChartHelper.hideChartLoadingIndicator(chartContainer);
                ChartHelper.enableFilters(filterSelectors);
                if (editTargetBtn) editTargetBtn.disabled = false;

                // Check if targets don't exist
                if (data.no_targets) {
                    showNoTargetsMessage(data);
                    return;
                }

                // Check if the response contains an error
                if (data.error) {
                    console.error("Server error:", data.error);
                    ChartHelper.showErrorMessage(
                        targetRevenueChart,
                        ctx,
                        data.error
                    );
                    return;
                }

                // Check if there's a message indicating no data
                if (data.message) {
                    console.log("Server message:", data.message);
                    ChartHelper.showNoDataMessage(
                        targetRevenueChart,
                        ctx,
                        data.message
                    );
                    return;
                }

                clearMessages();
                hideNoTargetsMessage();
                updateTargetRevenueChart(data);
            })
            .catch((error) => {
                console.error("Error fetching Target Revenue data:", error);
                ChartHelper.hideChartLoadingIndicator(chartContainer);
                ChartHelper.enableFilters(filterSelectors);
                if (editTargetBtn) editTargetBtn.disabled = false;
                ChartHelper.showErrorMessage(
                    targetRevenueChart,
                    ctx,
                    "Failed to load chart data. Please try again."
                );
            });
    }

    const triggerUpdate = () => {
        const view = viewSelect.value;
        const month = monthSelect.value;
        const year = yearSelect.value;
        const category = categorySelect.value;

        if (month && year && category) {
            fetchAndUpdateTargetChart(month, year, category, view);
        }
    };

    // Event listeners
    viewSelect.addEventListener("change", triggerUpdate);
    monthSelect.addEventListener("change", triggerUpdate);
    yearSelect.addEventListener("change", triggerUpdate);
    categorySelect.addEventListener("change", triggerUpdate);

    // Input Target button click handler
    inputTargetBtn.addEventListener("click", function () {
        const month = monthSelect.value;
        const year = yearSelect.value;
        const category = categorySelect.value;

        const url = `/branch-target/input?month=${month}&year=${year}&category=${encodeURIComponent(
            category
        )}`;
        window.location.href = url;
    });

    // Edit Target button click handler
    editTargetBtn.addEventListener("click", function () {
        const month = monthSelect.value;
        const year = yearSelect.value;
        const category = categorySelect.value;

        const url = `/branch-target/input?month=${month}&year=${year}&category=${encodeURIComponent(
            category
        )}&edit=1`;
        window.location.href = url;
    });

    // Read URL parameters and set default values
    const urlParams = new URLSearchParams(window.location.search);
    const urlView = urlParams.get("view");
    const urlMonth = urlParams.get("month");
    const urlYear = urlParams.get("year");
    const urlCategory = urlParams.get("category");

    // Set default values from URL parameters
    if (urlView) {
        viewSelect.value = urlView;
    }

    if (urlMonth) {
        monthSelect.value = urlMonth;
    } else {
        const currentMonth = new Date().getMonth() + 1;
        monthSelect.value = currentMonth;
    }

    if (urlYear) {
        yearSelect.value = urlYear;
    }

    if (urlCategory) {
        categorySelect.value = decodeURIComponent(urlCategory);
    }

    // Scroll to target-revenue section if URL has hash
    if (window.location.hash === "#target-revenue-section") {
        setTimeout(() => {
            const targetSection = document.getElementById(
                "target-revenue-section"
            );
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }
        }, 100);
    }

    // Three-dots menu toggle
    const targetMenuButton = document.getElementById("targetMenuButton");
    const targetDropdownMenu = document.getElementById("targetDropdownMenu");

    if (targetMenuButton && targetDropdownMenu) {
        // Toggle dropdown on button click
        targetMenuButton.addEventListener("click", function (e) {
            e.stopPropagation();
            targetDropdownMenu.classList.toggle("hidden");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (e) {
            if (
                !targetMenuButton.contains(e.target) &&
                !targetDropdownMenu.contains(e.target)
            ) {
                targetDropdownMenu.classList.add("hidden");
            }
        });
    }

    // Refresh Data functionality
    const targetRefreshBtn = document.getElementById("targetRefreshDataBtn");
    if (targetRefreshBtn) {
        targetRefreshBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (targetDropdownMenu) {
                targetDropdownMenu.classList.add("hidden");
            }

            // Get current filter values and refresh the chart
            const view = viewSelect.value;
            const month = monthSelect.value;
            const year = yearSelect.value;
            const category = categorySelect.value;

            if (month && year && category) {
                fetchAndUpdateTargetChart(month, year, category, view);
            }
        });
    }

    // Export to Excel functionality
    const targetExportBtn = document.getElementById("targetExportExcelBtn");
    if (targetExportBtn) {
        targetExportBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const view = viewSelect.value;
            const month = monthSelect.value;
            const year = yearSelect.value;
            const category = categorySelect.value;

            if (!month || !year || !category) {
                alert("Please select month, year, and category");
                return;
            }

            // Close dropdown
            if (targetDropdownMenu) {
                targetDropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = targetExportBtn.innerHTML;
            targetExportBtn.disabled = true;
            targetExportBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportUrl = `/target-revenue/export-excel?month=${month}&year=${year}&category=${encodeURIComponent(
                category
            )}&view=${view}`;

            // Use window.location for direct download
            window.location.href = exportUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                targetExportBtn.disabled = false;
                targetExportBtn.innerHTML = originalContent;
            }, 2000);
        });
    }

    // Export to PDF functionality
    const targetExportPdfBtn = document.getElementById("targetExportPdfBtn");
    if (targetExportPdfBtn) {
        targetExportPdfBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const month = monthSelect.value;
            const year = yearSelect.value;
            const category = categorySelect.value;

            if (!month || !year || !category) {
                alert("Please select month, year, and category");
                return;
            }

            // Close dropdown
            if (targetDropdownMenu) {
                targetDropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = targetExportPdfBtn.innerHTML;
            targetExportPdfBtn.disabled = true;
            targetExportPdfBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportPdfUrl = `/target-revenue/export-pdf?month=${month}&year=${year}&category=${encodeURIComponent(
                category
            )}`;

            // Use window.location for direct download
            window.location.href = exportPdfUrl;

            // Reset button state after a short delay
            setTimeout(() => {
                targetExportPdfBtn.disabled = false;
                targetExportPdfBtn.innerHTML = originalContent;
            }, 2000);
        });
    }

    // Initialize
    triggerUpdate();
});

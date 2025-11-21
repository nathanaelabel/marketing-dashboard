// Helper function to format date for display
function formatDisplayDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = String(date.getFullYear()).substr(-2);
    return `${day}.${month}.${year}`;
}

// Update all date headers in the table
function updateDateHeaders(dateString) {
    const formattedDate = formatDisplayDate(dateString);
    const headers = [
        "sales-date-header",
        "stok-date-header",
        "bdp-date-header",
        "stok-bdp-date-header",
        "total-date-header",
    ];
    headers.forEach((headerId) => {
        const header = document.getElementById(headerId);
        if (header) {
            header.textContent = formattedDate;
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    // Initialize date headers
    const dateInput = document.getElementById("sales-comp-date-select");
    if (dateInput) {
        updateDateHeaders(dateInput.value);
    }

    // Initialize TableHelper for Sales Comparison (No pagination - show all 17 branches)
    const salesComparisonTable = new TableHelper({
        apiEndpoint: "/sales-comparison/data",

        // Set longer timeout for complex queries (5 minutes)
        requestTimeout: 300000, // 300 seconds (5 minutes)

        // Disable pagination for this table
        disablePagination: true,

        // Override selectors to use sales-comp- prefixed IDs
        tableBodySelector: "#sales-comp-table-body",
        loadingSelector: "#sales-comp-loading-indicator",
        errorSelector: "#sales-comp-error-message",
        errorTextSelector: "#sales-comp-error-text",
        noDataSelector: "#sales-comp-no-data-message",
        tableContainerSelector: "#sales-comp-table-container",
        periodInfoSelector: "#sales-comp-period-info",

        // Override getAdditionalFilters to include ONLY date (not month/year)
        getAdditionalFilters: function () {
            const dateSelect = document.getElementById(
                "sales-comp-date-select"
            );
            return {
                date: dateSelect
                    ? dateSelect.value
                    : new Date().toISOString().split("T")[0],
            };
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML =
                    '<tr><td colspan="17" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update period info with formatted date
            if (this.elements.periodInfo && data.formatted_date) {
                this.elements.periodInfo.textContent = data.formatted_date;
            }

            // Update date headers
            if (data.date) {
                updateDateHeaders(data.date);
            }

            const rows = data.data
                .map((item) => {
                    const formatCurrency = (value) => {
                        if (!value || value === 0) return "-";
                        return TableHelper.formatCurrency(value);
                    };

                    return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-center">${
                            item.no
                        }</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${
                            item.branch_code
                        }</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.sales_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.sales_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_sales
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.stok_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.stok_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_stok
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.bdp_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.bdp_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_bdp
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.stok_bdp_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.stok_bdp_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_stok_bdp
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            item.total_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 text-right">${formatCurrency(
                            item.grand_total
                        )}</td>
                    </tr>
                `;
                })
                .join("");

            this.elements.tableBody.innerHTML = rows;
        },
    });

    // Add event listener for date change
    if (dateInput) {
        dateInput.addEventListener("change", () => {
            updateDateHeaders(dateInput.value);
            salesComparisonTable.currentPage = 1;
            salesComparisonTable.loadData();
        });
    }

    // Initialize the table
    salesComparisonTable.init();

    // Three-dots menu toggle
    const menuButton = document.getElementById("scMenuButton");
    const dropdownMenu = document.getElementById("scDropdownMenu");

    if (menuButton && dropdownMenu) {
        // Toggle dropdown on button click
        menuButton.addEventListener("click", function (e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle("hidden");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (e) {
            if (
                !menuButton.contains(e.target) &&
                !dropdownMenu.contains(e.target)
            ) {
                dropdownMenu.classList.add("hidden");
            }
        });
    }

    // Refresh Data functionality
    const refreshBtn = document.getElementById("scRefreshDataBtn");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Refresh the table data
            salesComparisonTable.loadData();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById("scExportExcelBtn");
    if (exportBtn) {
        exportBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentDate = document.getElementById(
                "sales-comp-date-select"
            ).value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportUrl = `/sales-comparison/export-excel?date=${currentDate}`;

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
    const exportPdfBtn = document.getElementById("scExportPdfBtn");
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentDate = document.getElementById(
                "sales-comp-date-select"
            ).value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportPdfUrl = `/sales-comparison/export-pdf?date=${currentDate}`;

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

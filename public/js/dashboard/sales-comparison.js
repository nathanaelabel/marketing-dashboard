// Helper function to format date for display (dd.mm.yy format)
function formatDisplayDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = String(date.getFullYear()).substr(-2);
    return `${day}.${month}.${year}`;
}

// Update all date headers in the table
function updateDateHeaders(salesDate, stokBdpDate) {
    const formattedSalesDate = formatDisplayDate(salesDate);
    const formattedStokBdpDate = formatDisplayDate(stokBdpDate);

    // Sales header uses yesterday's date
    const salesHeader = document.getElementById("sales-date-header");
    if (salesHeader) {
        salesHeader.textContent = formattedSalesDate;
    }

    // Stok, BDP, and combined headers use today's date
    const todayHeaders = [
        "stok-date-header",
        "bdp-date-header",
        "stok-bdp-date-header",
        "total-date-header",
    ];
    todayHeaders.forEach((headerId) => {
        const header = document.getElementById(headerId);
        if (header) {
            header.textContent = formattedStokBdpDate;
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    // Initialize date headers immediately on page load
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    // Format dates as YYYY-MM-DD for updateDateHeaders function
    const todayStr = today.toISOString().split("T")[0];
    const yesterdayStr = yesterday.toISOString().split("T")[0];

    // Set initial date headers before data loads
    updateDateHeaders(yesterdayStr, todayStr);

    // Initialize TableHelper for Sales Comparison (No pagination - show all 17 branches)
    const salesComparisonTable = new TableHelper({
        apiEndpoint: "/sales-comparison/data",

        // Set longer timeout for realtime queries to branch databases (3 minutes)
        requestTimeout: 180000, // 180 seconds (3 minutes)

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

        // No additional filters needed - backend auto-detects dates
        getAdditionalFilters: function () {
            return {};
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML =
                    '<tr><td colspan="17" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update period display with today's date
            const periodDisplay = document.getElementById(
                "sales-comp-period-display"
            );
            if (periodDisplay && data.formatted_date) {
                periodDisplay.textContent = data.formatted_date;
            }

            // Update date headers with sales and stok/bdp dates
            if (data.sales_date && data.stok_bdp_date) {
                updateDateHeaders(data.sales_date, data.stok_bdp_date);
            }

            const formatCurrency = (value) => {
                if (!value || value === 0) return "-";
                return TableHelper.formatCurrency(value);
            };

            const rows = data.data
                .map((item) => {
                    // Check if connection failed for this branch
                    if (item.connection_failed) {
                        return `
                        <tr class="hover:bg-gray-50 bg-red-50">
                            <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-center">${item.no}</td>
                            <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.branch_code}</td>
                            <td colspan="15" class="px-3 py-2 text-sm text-red-600 text-center font-semibold">Connection failed. Please try again.</td>
                        </tr>
                        `;
                    }

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

            // Add TOTAL row if totals data exists
            let totalRow = "";
            if (data.totals) {
                totalRow = `
                    <tr class="bg-gray-100 font-bold border-t-2 border-gray-400">
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-center" colspan="2">TOTAL</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.sales_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.sales_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_sales
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.stok_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.stok_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_stok
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.bdp_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.bdp_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_bdp
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.stok_bdp_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.stok_bdp_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_stok_bdp
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_mika
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(
                            data.totals.total_sparepart
                        )}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 text-right">${formatCurrency(
                            data.totals.grand_total
                        )}</td>
                    </tr>
                `;
            }

            this.elements.tableBody.innerHTML = rows + totalRow;
        },
    });

    // Initialize the table (no date filter needed)
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

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = "Exporting...";

            // Create download URL (no date parameter needed - auto-detect)
            const exportUrl = `/sales-comparison/export-excel`;

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

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = "Exporting...";

            // Create download URL (no date parameter needed - auto-detect)
            const exportPdfUrl = `/sales-comparison/export-pdf`;

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

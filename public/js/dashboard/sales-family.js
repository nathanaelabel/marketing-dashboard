// Update section title based on data type
function updateFamilySectionTitle(type) {
    const sectionTitle = document.getElementById("family-section-title");
    if (sectionTitle) {
        sectionTitle.textContent =
            type === "pcs"
                ? "Penjualan Per Family (Pcs)"
                : "Penjualan Per Family (Rp)";
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Set initial title
    updateFamilySectionTitle("rp");

    // Initialize date inputs
    const startDateInput = document.getElementById("sf-start-date");
    const endDateInput = document.getElementById("sf-end-date");

    // Check if date inputs exist
    if (!startDateInput || !endDateInput) {
        console.error("Date input elements not found");
        return;
    }

    // Check if flatpickr is available
    if (typeof flatpickr === "undefined") {
        console.error("Flatpickr library not loaded");
        return;
    }

    // Initialize TableHelper for Sales Family with type toggle functionality
    const salesFamilyTable = new TableHelper({
        apiEndpoint: "/sales-family/data",

        // Configure search functionality for family names
        searchInputSelector: "#family-search-input",
        searchField: "family_name",

        // Configure entries per page selector
        entriesPerPageSelector: "#family-entries-per-page",

        // Override selectors to use family- prefixed IDs
        tableBodySelector: "#family-table-body",
        loadingSelector: "#family-loading-indicator",
        errorSelector: "#family-error-message",
        errorTextSelector: "#family-error-text",
        noDataSelector: "#family-no-data-message",
        tableContainerSelector: "#family-table-container",
        paginationInfoSelector: "#family-pagination-info",
        periodInfoSelector: "#family-period-info",
        prevPageBtnSelector: "#family-prev-page",
        nextPageBtnSelector: "#family-next-page",
        pageNumbersSelector: "#family-page-numbers",

        // Override getAdditionalFilters to include type and dates
        getAdditionalFilters: function () {
            const typeSelect = document.getElementById("family-type-select");
            return {
                type: typeSelect ? typeSelect.value : "rp",
                start_date: startDateInput ? startDateInput.value : "",
                end_date: endDateInput ? endDateInput.value : "",
            };
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML =
                    '<tr><td colspan="20" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update section title based on data type
            updateFamilySectionTitle(data.type);

            // Dynamic column configuration based on data type
            const isQuantity = data.type === "pcs";
            const columns = [
                { field: "no", type: "text", align: "left" },
                {
                    field: "family_name",
                    type: "text",
                    align: "left",
                    maxWidth: "xs",
                },
                ...TableHelper.getBranchCodes().map((code) => ({
                    field: code.toLowerCase(),
                    type: isQuantity ? "number" : "currency",
                    align: "right",
                })),
                {
                    field: "nasional",
                    type: isQuantity ? "number" : "currency",
                    align: "right",
                    class: "font-medium bg-blue-50",
                },
            ];

            const rows = data.data
                .map((item) =>
                    TableHelper.buildTableRow(item, columns, {
                        rowClass: "hover:bg-gray-50",
                        cellClass: "px-3 py-2 text-sm text-gray-900",
                    })
                )
                .join("");

            this.elements.tableBody.innerHTML = rows;
        },
    });

    // Initialize Flatpickr date pickers
    let startDatePicker, endDatePicker;

    const triggerUpdate = () => {
        const currentStartDate = startDateInput.value;
        const currentEndDate = endDateInput.value;
        if (currentStartDate && currentEndDate) {
            // Validate date range (max 1 year)
            const start = new Date(currentStartDate);
            const end = new Date(currentEndDate);
            const daysDiff = Math.floor((end - start) / (1000 * 60 * 60 * 24));

            if (daysDiff > 365) {
                alert(
                    "Date range too large! Maximum date range is 1 year (365 days). Please select a smaller date range."
                );
                return;
            }

            // Reset to page 1 and reload data from server
            salesFamilyTable.currentPage = 1;
            salesFamilyTable.loadData();
        }
    };

    // Use yesterday (H-1) as max date since dashboard is updated daily
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    // Set minimum date to 2020-01-01
    const minDate = new Date(2020, 0, 1);

    startDatePicker = flatpickr(startDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        minDate: minDate,
        maxDate: endDateInput.value || yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (endDatePicker) {
                endDatePicker.set("minDate", selectedDates[0]);
            }
            triggerUpdate();
        },
    });

    endDatePicker = flatpickr(endDateInput, {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        minDate: startDateInput.value || minDate,
        maxDate: yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (startDatePicker) {
                startDatePicker.set("maxDate", selectedDates[0]);
            }
            triggerUpdate();
        },
    });

    // Add event listener for type selector
    const typeSelect = document.getElementById("family-type-select");
    if (typeSelect) {
        typeSelect.addEventListener("change", (e) => {
            updateFamilySectionTitle(e.target.value);
            salesFamilyTable.currentPage = 1;
            salesFamilyTable.loadData();
        });
    }

    // Initialize the table
    salesFamilyTable.init();

    // Three-dots menu toggle
    const menuButton = document.getElementById("sfMenuButton");
    const dropdownMenu = document.getElementById("sfDropdownMenu");

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
    const refreshBtn = document.getElementById("sfRefreshDataBtn");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Refresh the table data
            salesFamilyTable.loadData();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById("sfExportExcelBtn");
    if (exportBtn) {
        exportBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType =
                document.getElementById("family-type-select").value;

            if (!currentStartDate || !currentEndDate) {
                alert("Please select both start and end dates");
                return;
            }

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportUrl = `/sales-family/export-excel?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

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
    const exportPdfBtn = document.getElementById("sfExportPdfBtn");
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType =
                document.getElementById("family-type-select").value;

            if (!currentStartDate || !currentEndDate) {
                alert("Please select both start and end dates");
                return;
            }

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = "Exporting...";

            // Create download URL with parameters
            const exportPdfUrl = `/sales-family/export-pdf?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

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

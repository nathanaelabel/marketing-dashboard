document.addEventListener("DOMContentLoaded", function () {
    // Function to update section title
    function updateSectionTitle(type) {
        const sectionTitle = document.getElementById("section-title");
        if (sectionTitle) {
            sectionTitle.textContent =
                type === "pcs"
                    ? "Penjualan Per Item (Pcs)"
                    : "Penjualan Per Item (Rp)";
        }
    }

    // Initialize date inputs
    const startDateInput = document.getElementById("si-start-date");
    const endDateInput = document.getElementById("si-end-date");

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

    // Initialize TableHelper with type support
    const salesItemTable = new TableHelper({
        apiEndpoint: "/sales-item/data",

        // Configure search functionality
        searchInputSelector: "#search-input",
        searchField: "product_name",

        // Configure entries per page selector
        entriesPerPageSelector: "#entries-per-page",

        // Add type filter selector
        typeSelectSelector: "#type-select",

        // Override getAdditionalFilters to include type and dates
        getAdditionalFilters: function () {
            const typeSelect = document.getElementById("type-select");
            return {
                type: typeSelect ? typeSelect.value : "rp",
                start_date: startDateInput ? startDateInput.value : "",
                end_date: endDateInput ? endDateInput.value : "",
            };
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML =
                    '<tr><td colspan="21" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update section title based on current type
            updateSectionTitle(data.type || "rp");

            // Dynamic formatting based on data type
            const isRp = data.type === "rp";
            const formatValue = (value) =>
                isRp
                    ? TableHelper.formatCurrency(value)
                    : TableHelper.formatNumber(value);

            const rows = data.data
                .map((item) => {
                    return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${
                            item.no
                        }</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 max-w-xs">
                            <div class="truncate" title="${
                                item.product_name
                            }">${item.product_name}</div>
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${
                            item.product_status || "-"
                        }</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.mdn
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.mks
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.plb
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.dps
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.sby
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.pku
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.crb
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.tgr
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.bks
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.smg
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.bjm
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.bdg
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.lmp
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.jkt
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.ptk
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.pwt
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(
                            item.pdg
                        )}</td>
                        <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 bg-blue-50">${formatValue(
                            item.nasional
                        )}</td>
                    </tr>
                `;
                })
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
            salesItemTable.currentPage = 1;
            salesItemTable.loadData();
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
        defaultDate: startDateInput.value,
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
        defaultDate: endDateInput.value,
        minDate: startDateInput.value || minDate,
        maxDate: yesterday,
        onChange: function (selectedDates, dateStr, instance) {
            if (startDatePicker) {
                startDatePicker.set("maxDate", selectedDates[0]);
            }
            triggerUpdate();
        },
    });

    // Add event listener for type changes (needs server reload)
    const typeSelect = document.getElementById("type-select");
    if (typeSelect) {
        typeSelect.addEventListener("change", function () {
            // Update title immediately when dropdown changes
            updateSectionTitle(this.value);
            // Reset to page 1 and reload data from server
            salesItemTable.currentPage = 1;
            salesItemTable.loadData();
        });
    }

    // Initialize the table
    salesItemTable.init();

    // Set initial title based on default selection
    updateSectionTitle("rp");

    // Three-dots menu toggle
    const menuButton = document.getElementById("siMenuButton");
    const dropdownMenu = document.getElementById("siDropdownMenu");

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
    const refreshBtn = document.getElementById("siRefreshDataBtn");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add("hidden");
            }

            // Refresh the table data
            salesItemTable.loadData();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById("siExportExcelBtn");
    if (exportBtn) {
        exportBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType = document.getElementById("type-select").value;

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
            const exportUrl = `/sales-item/export-excel?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

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
    const exportPdfBtn = document.getElementById("siExportPdfBtn");
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentStartDate = startDateInput.value;
            const currentEndDate = endDateInput.value;
            const currentType = document.getElementById("type-select").value;

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
            const exportPdfUrl = `/sales-item/export-pdf?start_date=${currentStartDate}&end_date=${currentEndDate}&type=${currentType}`;

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

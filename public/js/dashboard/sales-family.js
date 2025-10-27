// Update section title based on data type
function updateFamilySectionTitle(type) {
    const sectionTitle = document.getElementById('family-section-title');
    if (sectionTitle) {
        sectionTitle.textContent = type === 'pcs' ? 'Penjualan Per Family (Pcs)' : 'Penjualan Per Family (Rp)';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Set initial title
    updateFamilySectionTitle('rp');

    // Initialize TableHelper for Sales Family with type toggle functionality
    const salesFamilyTable = new TableHelper({
        apiEndpoint: '/sales-family/data',

        // Configure search functionality for family names
        searchInputSelector: '#family-search-input',
        searchField: 'family_name',

        // Configure entries per page selector
        entriesPerPageSelector: '#family-entries-per-page',

        // Override selectors to use family- prefixed IDs
        monthSelectSelector: '#family-month-select',
        yearSelectSelector: '#family-year-select',
        tableBodySelector: '#family-table-body',
        loadingSelector: '#family-loading-indicator',
        errorSelector: '#family-error-message',
        errorTextSelector: '#family-error-text',
        noDataSelector: '#family-no-data-message',
        tableContainerSelector: '#family-table-container',
        paginationInfoSelector: '#family-pagination-info',
        periodInfoSelector: '#family-period-info',
        prevPageBtnSelector: '#family-prev-page',
        nextPageBtnSelector: '#family-next-page',
        pageNumbersSelector: '#family-page-numbers',

        // Override getAdditionalFilters to include type
        getAdditionalFilters: function () {
            const typeSelect = document.getElementById('family-type-select');
            return {
                type: typeSelect ? typeSelect.value : 'rp'
            };
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML = '<tr><td colspan="20" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update section title based on data type
            updateFamilySectionTitle(data.type);

            // Dynamic column configuration based on data type
            const isQuantity = data.type === 'pcs';
            const columns = [
                { field: 'no', type: 'text', align: 'left' },
                { field: 'family_name', type: 'text', align: 'left', maxWidth: 'xs' },
                ...TableHelper.getBranchCodes().map(code => ({
                    field: code.toLowerCase(),
                    type: isQuantity ? 'number' : 'currency',
                    align: 'right'
                })),
                { field: 'nasional', type: isQuantity ? 'number' : 'currency', align: 'right', class: 'font-medium bg-blue-50' }
            ];

            const rows = data.data.map(item =>
                TableHelper.buildTableRow(item, columns, {
                    rowClass: 'hover:bg-gray-50',
                    cellClass: 'px-3 py-2 text-sm text-gray-900'
                })
            ).join('');

            this.elements.tableBody.innerHTML = rows;
        }
    });

    // Add event listener for type selector
    const typeSelect = document.getElementById('family-type-select');
    if (typeSelect) {
        typeSelect.addEventListener('change', (e) => {
            updateFamilySectionTitle(e.target.value);
            salesFamilyTable.currentPage = 1;
            salesFamilyTable.loadData();
        });
    }


    // Initialize the table
    salesFamilyTable.init();

    // Three-dots menu toggle
    const menuButton = document.getElementById('sfMenuButton');
    const dropdownMenu = document.getElementById('sfDropdownMenu');

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
    const refreshBtn = document.getElementById('sfRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Refresh the table data
            salesFamilyTable.loadData();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('sfExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentMonth = document.getElementById('family-month-select').value;
            const currentYear = document.getElementById('family-year-select').value;
            const currentType = document.getElementById('family-type-select').value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportUrl = `/sales-family/export-excel?month=${currentMonth}&year=${currentYear}&type=${currentType}`;

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
    const exportPdfBtn = document.getElementById('sfExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentMonth = document.getElementById('family-month-select').value;
            const currentYear = document.getElementById('family-year-select').value;
            const currentType = document.getElementById('family-type-select').value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportPdfUrl = `/sales-family/export-pdf?month=${currentMonth}&year=${currentYear}&type=${currentType}`;

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

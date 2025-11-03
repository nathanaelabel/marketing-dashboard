document.addEventListener('DOMContentLoaded', function () {
    // Initialize TableHelper for Return Comparison (No pagination - show all 17 branches)
    const returnComparisonTable = new TableHelper({
        apiEndpoint: '/return-comparison/data',

        // Override selectors to use return- prefixed IDs
        monthSelectSelector: '#return-month-select',
        yearSelectSelector: '#return-year-select',
        tableBodySelector: '#return-table-body',
        loadingSelector: '#return-loading-indicator',
        errorSelector: '#return-error-message',
        errorTextSelector: '#return-error-text',
        noDataSelector: '#return-no-data-message',
        tableContainerSelector: '#return-table-container',
        periodInfoSelector: '#return-period-info',

        // Disable pagination for this table
        disablePagination: true,

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML = '<tr><td colspan="13" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            const rows = data.data.map(item => {
                const formatNumber = (value) => {
                    if (!value || value === 0) return '-';
                    return TableHelper.formatNumber(value);
                };

                const formatCurrency = (value) => {
                    if (!value || value === 0) return '-';
                    return TableHelper.formatCurrency(value);
                };

                const formatPercent = (value) => {
                    if (!value || value === 0) return '-';
                    return value.toFixed(2) + '%';
                };

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-center">${item.no}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.branch_code}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatNumber(item.sales_bruto_pc)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(item.sales_bruto_rp)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatNumber(item.cnc_pc)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(item.cnc_rp)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatPercent(item.cnc_percent)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatNumber(item.barang_pc)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(item.barang_rp)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatPercent(item.barang_percent)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatNumber(item.cabang_pabrik_pc)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 text-right">${formatCurrency(item.cabang_pabrik_rp)}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 text-right">${formatPercent(item.cabang_pabrik_percent)}</td>
                    </tr>
                `;
            }).join('');

            this.elements.tableBody.innerHTML = rows;
        }
    });

    // Initialize the table
    returnComparisonTable.init();

    // Three-dots menu toggle
    const menuButton = document.getElementById('rcMenuButton');
    const dropdownMenu = document.getElementById('rcDropdownMenu');

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
    const refreshBtn = document.getElementById('rcRefreshDataBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Refresh the table data
            returnComparisonTable.loadData();
        });
    }

    // Export to Excel functionality
    const exportBtn = document.getElementById('rcExportExcelBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentMonth = document.getElementById('return-month-select').value;
            const currentYear = document.getElementById('return-year-select').value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportUrl = `/return-comparison/export-excel?month=${currentMonth}&year=${currentYear}`;

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
    const exportPdfBtn = document.getElementById('rcExportPdfBtn');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const currentMonth = document.getElementById('return-month-select').value;
            const currentYear = document.getElementById('return-year-select').value;

            // Close dropdown
            if (dropdownMenu) {
                dropdownMenu.classList.add('hidden');
            }

            // Show loading state
            const originalContent = exportPdfBtn.innerHTML;
            exportPdfBtn.disabled = true;
            exportPdfBtn.innerHTML = 'Exporting...';

            // Create download URL with parameters
            const exportPdfUrl = `/return-comparison/export-pdf?month=${currentMonth}&year=${currentYear}`;

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


document.addEventListener('DOMContentLoaded', function () {

    // Function to update section title
    function updateSectionTitle(type) {
        const sectionTitle = document.getElementById('section-title');
        if (sectionTitle) {
            sectionTitle.textContent = type === 'pcs' ?
                'Penjualan Per Item (Pcs)' :
                'Penjualan Per Item (Rp)';
        }
    }

    // Initialize TableHelper with type support
    const salesItemTable = new TableHelper({
        apiEndpoint: '/sales-item/data',

        // Configure search functionality
        searchInputSelector: '#search-input',
        searchField: 'product_name',

        // Configure entries per page selector
        entriesPerPageSelector: '#entries-per-page',

        // Add type filter selector
        typeSelectSelector: '#type-select',

        // Override getAdditionalFilters to include type
        getAdditionalFilters: function () {
            const typeSelect = document.getElementById('type-select');
            return {
                type: typeSelect ? typeSelect.value : 'rp'
            };
        },

        renderTable: function (data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML = '<tr><td colspan="21" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Update section title based on current type
            updateSectionTitle(data.type || 'rp');

            // Dynamic formatting based on data type
            const isRp = data.type === 'rp';
            const formatValue = (value) => isRp ? TableHelper.formatCurrency(value) : TableHelper.formatNumber(value);

            const rows = data.data.map(item => {
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.no}</td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 max-w-xs">
                            <div class="truncate" title="${item.product_name}">${item.product_name}</div>
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.product_status || '-'}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.mdn)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.mks)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.plb)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.dps)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.sby)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.pku)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.crb)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.tgr)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.bks)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.smg)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.bjm)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.bdg)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.lmp)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.jkt)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.ptk)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.pwt)}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatValue(item.pdg)}</td>
                        <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 bg-blue-50">${formatValue(item.nasional)}</td>
                    </tr>
                `;
            }).join('');

            this.elements.tableBody.innerHTML = rows;
        }
    });

    // Add event listener for type changes (needs server reload)
    const typeSelect = document.getElementById('type-select');
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
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
    updateSectionTitle('rp');
});

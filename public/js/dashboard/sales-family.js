document.addEventListener('DOMContentLoaded', function () {
    // Initialize TableHelper for Sales Family with type toggle functionality
    const salesFamilyTable = new TableHelper({
        apiEndpoint: '/sales-family/data',
        
        // Add type filter selector
        typeSelectSelector: '#type-select',
        
        // Override getAdditionalFilters to include type
        getAdditionalFilters: function() {
            const typeSelect = document.getElementById('type-select');
            return {
                type: typeSelect ? typeSelect.value : 'amount'
            };
        },
        
        renderTable: function(data) {
            if (!data.data || data.data.length === 0) {
                this.elements.tableBody.innerHTML = '<tr><td colspan="21" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
                return;
            }

            // Dynamic column configuration based on data type
            const isQuantity = data.type === 'quantity';
            const columns = [
                { field: 'no', type: 'text', align: 'left' },
                { field: 'family_name', type: 'text', align: 'left', maxWidth: 'xs' },
                { field: 'family_code', type: 'text', align: 'left' },
                ...TableHelper.getBranchCodes().map(code => ({ 
                    field: code, 
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
    const typeSelect = document.getElementById('type-select');
    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            salesFamilyTable.currentPage = 1;
            salesFamilyTable.loadData();
        });
    }

    // Initialize the table
    salesFamilyTable.init();
});

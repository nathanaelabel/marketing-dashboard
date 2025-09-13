document.addEventListener('DOMContentLoaded', function () {
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const loadingIndicator = document.getElementById('loading-indicator');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const noDataMessage = document.getElementById('no-data-message');
    const tableContainer = document.getElementById('table-container');
    const tableBody = document.getElementById('table-body');
    const periodInfo = document.getElementById('period-info');
    const paginationInfo = document.getElementById('pagination-info');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageNumbers = document.getElementById('page-numbers');

    let currentPage = 1;
    let totalPages = 1;
    let currentData = null;

    // Set default values
    const currentDate = new Date();
    monthSelect.value = currentDate.getMonth() + 1;
    yearSelect.value = currentDate.getFullYear();

    // Event listeners
    monthSelect.addEventListener('change', () => {
        currentPage = 1;
        loadSalesItemData();
    });
    
    yearSelect.addEventListener('change', () => {
        currentPage = 1;
        loadSalesItemData();
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadSalesItemData();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadSalesItemData();
        }
    });

    function showLoading() {
        hideMessages();
        loadingIndicator.classList.remove('hidden');
        tableContainer.classList.add('hidden');
    }

    function hideLoading() {
        loadingIndicator.classList.add('hidden');
    }

    function hideMessages() {
        loadingIndicator.classList.add('hidden');
        errorMessage.classList.add('hidden');
        noDataMessage.classList.add('hidden');
    }

    function showError(message) {
        hideMessages();
        errorText.textContent = message;
        errorMessage.classList.remove('hidden');
        tableContainer.classList.add('hidden');
    }

    function showNoData() {
        hideMessages();
        noDataMessage.classList.remove('hidden');
        tableContainer.classList.add('hidden');
    }

    function showTable() {
        hideMessages();
        tableContainer.classList.remove('hidden');
    }

    function formatCurrency(value) {
        if (!value || value === 0) return '-';
        
        // Format number with thousand separators
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value).replace('IDR', 'Rp').trim();
    }

    function loadSalesItemData() {
        const month = monthSelect.value;
        const year = yearSelect.value;

        if (!month || !year) {
            return;
        }

        showLoading();

        const url = new URL('/sales-item/data', window.location.origin);
        url.searchParams.append('month', month);
        url.searchParams.append('year', year);
        url.searchParams.append('page', currentPage);

        fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoading();

            if (data.error) {
                showError(data.message || 'An error occurred while loading data.');
                return;
            }

            if (!data.data || data.data.length === 0) {
                showNoData();
                return;
            }

            currentData = data;
            renderTable(data);
            updatePagination(data.pagination);
            updatePeriodInfo(data.period);
            showTable();
        })
        .catch(error => {
            hideLoading();
            console.error('Error loading sales item data:', error);
            showError('Failed to load sales item data. Please try again.');
        });
    }

    function renderTable(data) {
        if (!data.data || data.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="21" class="px-3 py-4 text-center text-gray-500">No data available</td></tr>';
            return;
        }

        const rows = data.data.map(item => {
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.no}</td>
                    <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200 max-w-xs">
                        <div class="truncate" title="${item.nama_barang}">${item.nama_barang}</div>
                    </td>
                    <td class="px-3 py-2 text-sm text-gray-900 border-r border-gray-200">${item.ket_pl || '-'}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.mdn)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.mks)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.plb)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.dps)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.sby)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.pku)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.crb)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.tgr)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.bks)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.smg)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.bjm)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.bdg)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.lmp)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.jkt)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.ptk)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.pwt)}</td>
                    <td class="px-3 py-2 text-sm text-right text-gray-900 border-r border-gray-200">${formatCurrency(item.pdg)}</td>
                    <td class="px-3 py-2 text-sm text-right font-medium text-gray-900 bg-blue-50">${formatCurrency(item.nasional)}</td>
                </tr>
            `;
        }).join('');

        tableBody.innerHTML = rows;
    }

    function updatePagination(pagination) {
        if (!pagination) return;

        currentPage = pagination.current_page;
        totalPages = pagination.total_pages;

        // Update pagination info
        const startItem = ((currentPage - 1) * pagination.per_page) + 1;
        const endItem = Math.min(currentPage * pagination.per_page, pagination.total);
        paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${pagination.total} items`;

        // Update navigation buttons
        prevPageBtn.disabled = !pagination.has_prev;
        nextPageBtn.disabled = !pagination.has_next;

        // Generate page numbers
        generatePageNumbers(currentPage, totalPages);
    }

    function generatePageNumbers(current, total) {
        pageNumbers.innerHTML = '';

        if (total <= 1) return;

        const maxVisible = 5;
        let start = Math.max(1, current - Math.floor(maxVisible / 2));
        let end = Math.min(total, start + maxVisible - 1);

        // Adjust start if we're near the end
        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        // Add first page and dots if needed
        if (start > 1) {
            addPageButton(1);
            if (start > 2) {
                addPageDots();
            }
        }

        // Add page numbers
        for (let i = start; i <= end; i++) {
            addPageButton(i, i === current);
        }

        // Add dots and last page if needed
        if (end < total) {
            if (end < total - 1) {
                addPageDots();
            }
            addPageButton(total);
        }
    }

    function addPageButton(page, isActive = false) {
        const button = document.createElement('button');
        button.textContent = page;
        button.className = `px-3 py-1 border rounded-md text-sm ${
            isActive 
                ? 'bg-blue-600 text-white border-blue-600' 
                : 'text-gray-500 border-gray-300 hover:bg-gray-50'
        }`;
        
        if (!isActive) {
            button.addEventListener('click', () => {
                currentPage = page;
                loadSalesItemData();
            });
        }

        pageNumbers.appendChild(button);
    }

    function addPageDots() {
        const dots = document.createElement('span');
        dots.textContent = '...';
        dots.className = 'px-2 py-1 text-gray-500';
        pageNumbers.appendChild(dots);
    }

    function updatePeriodInfo(period) {
        if (period) {
            periodInfo.textContent = `${period.month_name} ${period.year}`;
        }
    }

    // Initial load
    loadSalesItemData();
});

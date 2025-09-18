/**
 * TableHelper - Reusable table utilities for dashboard tables
 * Provides common functionality for pagination, formatting, and UI state management
 */
class TableHelper {
    constructor(config) {
        this.config = {
            apiEndpoint: config.apiEndpoint || '',
            tableBodySelector: config.tableBodySelector || '#table-body',
            loadingSelector: config.loadingSelector || '#loading-indicator',
            errorSelector: config.errorSelector || '#error-message',
            errorTextSelector: config.errorTextSelector || '#error-text',
            noDataSelector: config.noDataSelector || '#no-data-message',
            tableContainerSelector: config.tableContainerSelector || '#table-container',
            periodInfoSelector: config.periodInfoSelector || '#period-info',
            paginationInfoSelector: config.paginationInfoSelector || '#pagination-info',
            prevPageBtnSelector: config.prevPageBtnSelector || '#prev-page',
            nextPageBtnSelector: config.nextPageBtnSelector || '#next-page',
            pageNumbersSelector: config.pageNumbersSelector || '#page-numbers',
            monthSelectSelector: config.monthSelectSelector || '#month-select',
            yearSelectSelector: config.yearSelectSelector || '#year-select',
            perPage: config.perPage || 50,
            ...config
        };

        this.currentPage = 1;
        this.totalPages = 1;
        this.currentData = null;
        this.allData = null; // Store all data for client-side operations
        this.filteredData = null; // Store filtered data
        this.perPage = 10; // Default entries per page

        this.initializeElements();
        this.bindEvents();
        this.setDefaultValues();
    }

    initializeElements() {
        this.elements = {};
        Object.keys(this.config).forEach(key => {
            if (key.endsWith('Selector')) {
                const elementKey = key.replace('Selector', '');
                this.elements[elementKey] = document.querySelector(this.config[key]);
            }
        });
    }

    bindEvents() {
        // Filter change events - these reload data from server
        if (this.elements.monthSelect) {
            this.elements.monthSelect.addEventListener('change', () => {
                this.currentPage = 1;
                this.loadData();
            });
        }

        if (this.elements.yearSelect) {
            this.elements.yearSelect.addEventListener('change', () => {
                this.currentPage = 1;
                this.loadData();
            });
        }

        // Client-side pagination events
        if (this.elements.prevPageBtn) {
            this.elements.prevPageBtn.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.renderCurrentPage();
                }
            });
        }

        if (this.elements.nextPageBtn) {
            this.elements.nextPageBtn.addEventListener('click', () => {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.renderCurrentPage();
                }
            });
        }

        // Entries per page change
        const entriesSelect = document.getElementById('entries-per-page');
        if (entriesSelect) {
            entriesSelect.addEventListener('change', () => {
                this.perPage = parseInt(entriesSelect.value);
                this.currentPage = 1;
                this.renderCurrentPage();
            });
        }

        // Search filter
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentPage = 1;
                    this.applyFiltersAndRender();
                }, 300); // Debounce search
            });
        }
    }

    setDefaultValues() {
        const currentDate = new Date();
        if (this.elements.monthSelect) {
            this.elements.monthSelect.value = currentDate.getMonth() + 1;
        }
        if (this.elements.yearSelect) {
            this.elements.yearSelect.value = currentDate.getFullYear();
        }
    }

    // UI State Management
    showLoading() {
        this.hideMessages();
        if (this.elements.loading) {
            this.elements.loading.classList.remove('hidden');
        }
        if (this.elements.tableContainer) {
            this.elements.tableContainer.classList.add('hidden');
        }
        // Disable pagination buttons during loading
        this.disablePaginationButtons(true);
    }

    hideLoading() {
        if (this.elements.loading) {
            this.elements.loading.classList.add('hidden');
        }
    }

    hideMessages() {
        ['loading', 'error', 'noData'].forEach(element => {
            if (this.elements[element]) {
                this.elements[element].classList.add('hidden');
            }
        });
    }

    showError(message) {
        this.hideMessages();
        if (this.elements.errorText) {
            this.elements.errorText.textContent = message;
        }
        if (this.elements.error) {
            this.elements.error.classList.remove('hidden');
        }
        if (this.elements.tableContainer) {
            this.elements.tableContainer.classList.add('hidden');
        }
    }

    showNoData() {
        this.hideMessages();
        if (this.elements.noData) {
            this.elements.noData.classList.remove('hidden');
        }
        if (this.elements.tableContainer) {
            this.elements.tableContainer.classList.add('hidden');
        }
    }

    showTable() {
        this.hideMessages();
        if (this.elements.tableContainer) {
            this.elements.tableContainer.classList.remove('hidden');
        }
        // Re-enable pagination buttons
        this.disablePaginationButtons(false);
    }

    disablePaginationButtons(disabled) {
        if (this.elements.prevPageBtn) {
            this.elements.prevPageBtn.disabled = disabled;
        }
        if (this.elements.nextPageBtn) {
            this.elements.nextPageBtn.disabled = disabled;
        }
        // Disable page number buttons
        if (this.elements.pageNumbers) {
            const pageButtons = this.elements.pageNumbers.querySelectorAll('button');
            pageButtons.forEach(btn => {
                btn.disabled = disabled;
            });
        }
    }

    // Formatting utilities
    static formatCurrency(value) {
        if (!value || value === 0) return '-';
        
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value).replace('IDR', 'Rp').trim();
    }

    static formatNumber(value, decimals = 0) {
        if (!value || value === 0) return '-';
        
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value);
    }

    static formatPercent(value, decimals = 1) {
        if (!value || value === 0) return '-';
        
        return new Intl.NumberFormat('id-ID', {
            style: 'percent',
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value / 100);
    }

    // Branch codes mapping
    static getBranchCodes() {
        return ['mdn', 'mks', 'plb', 'dps', 'sby', 'pku', 'crb', 'tgr', 'bks', 'smg', 'bjm', 'bdg', 'lmp', 'jkt', 'ptk', 'pwt', 'pdg'];
    }

    // API Request handling
    async loadData() {
        const filters = this.getFilters();
        
        if (!this.validateFilters(filters)) {
            return;
        }

        this.showLoading();

        try {
            // Shorter timeout for faster feedback
            const response = await this.fetchData(filters);
            this.handleDataResponse(response);
        } catch (error) {
            this.handleError(error);
        }
    }

    getFilters() {
        const filters = {
            page: this.currentPage
        };

        if (this.elements.monthSelect) {
            filters.month = this.elements.monthSelect.value;
        }
        if (this.elements.yearSelect) {
            filters.year = this.elements.yearSelect.value;
        }

        // Allow extending with additional filters
        if (this.config.getAdditionalFilters) {
            Object.assign(filters, this.config.getAdditionalFilters());
        }

        return filters;
    }

    validateFilters(filters) {
        // Basic validation - can be extended
        if (filters.month && (!filters.month || filters.month < 1 || filters.month > 12)) {
            this.showError('Invalid month selected');
            return false;
        }
        if (filters.year && (!filters.year || filters.year < 2020 || filters.year > 2030)) {
            this.showError('Invalid year selected');
            return false;
        }
        return true;
    }

    async fetchData(filters) {
        const url = new URL(this.config.apiEndpoint, window.location.origin);
        
        Object.keys(filters).forEach(key => {
            if (filters[key] !== undefined && filters[key] !== null) {
                url.searchParams.append(key, filters[key]);
            }
        });

        // Add cache busting to prevent browser cache issues
        url.searchParams.append('_', Date.now());

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Cache-Control': 'no-cache'
                },
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return response.json();
        } catch (error) {
            clearTimeout(timeoutId);
            throw error;
        }
    }

    handleDataResponse(data) {
        this.hideLoading();

        if (data.error) {
            this.showError(data.message || 'An error occurred while loading data.');
            return;
        }

        if (!data.data || data.data.length === 0) {
            this.showNoData();
            return;
        }

        // Store all data for client-side operations
        this.allData = data.data;
        this.currentData = data;
        this.currentPage = 1;
        
        // Apply filters and render current page
        this.applyFiltersAndRender();
        this.updatePeriodInfo(data.period);
        this.showTable();
    }

    handleError(error) {
        this.hideLoading();
        console.error('Error loading table data:', error);
        this.showError('Failed to load data. Please try again.');
    }

    // Client-side filtering and pagination methods
    applyFiltersAndRender() {
        if (!this.allData) return;

        // Apply search filter
        const searchInput = document.getElementById('search-input');
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

        if (searchTerm) {
            this.filteredData = this.allData.filter(item => {
                return item.product_name && item.product_name.toLowerCase().includes(searchTerm);
            });
        } else {
            this.filteredData = [...this.allData];
        }

        this.renderCurrentPage();
    }

    renderCurrentPage() {
        if (!this.filteredData) return;

        // Calculate pagination
        const totalItems = this.filteredData.length;
        this.totalPages = Math.ceil(totalItems / this.perPage);
        
        // Ensure current page is valid
        if (this.currentPage > this.totalPages && this.totalPages > 0) {
            this.currentPage = this.totalPages;
        }

        // Get current page data
        const startIndex = (this.currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const pageData = this.filteredData.slice(startIndex, endIndex);

        // Render table with current page data
        const dataForRender = {
            ...this.currentData,
            data: pageData
        };
        
        if (this.config.renderTable) {
            this.config.renderTable.call(this, dataForRender);
        } else {
            console.warn('renderTable method not implemented');
        }

        // Update pagination controls
        this.updateClientSidePagination(totalItems);
    }

    updateClientSidePagination(totalItems) {
        // Update pagination info
        if (this.elements.paginationInfo) {
            const startItem = totalItems === 0 ? 0 : ((this.currentPage - 1) * this.perPage) + 1;
            const endItem = Math.min(this.currentPage * this.perPage, totalItems);
            this.elements.paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalItems} entries`;
        }

        // Update navigation buttons
        if (this.elements.prevPageBtn) {
            this.elements.prevPageBtn.disabled = this.currentPage <= 1;
        }
        if (this.elements.nextPageBtn) {
            this.elements.nextPageBtn.disabled = this.currentPage >= this.totalPages;
        }

        // Generate page numbers
        this.generatePageNumbers(this.currentPage, this.totalPages);
    }

    // Table rendering - to be implemented by specific table types
    renderTable(data) {
        if (this.config.renderTable) {
            this.config.renderTable.call(this, data);
        } else {
            console.warn('renderTable method not implemented');
        }
    }

    // Pagination
    updatePagination(pagination) {
        if (!pagination) return;

        this.currentPage = pagination.current_page;
        this.totalPages = pagination.total_pages;

        // Update pagination info
        if (this.elements.paginationInfo) {
            const startItem = ((this.currentPage - 1) * pagination.per_page) + 1;
            const endItem = Math.min(this.currentPage * pagination.per_page, pagination.total);
            this.elements.paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${pagination.total} items`;
        }

        // Update navigation buttons
        if (this.elements.prevPageBtn) {
            this.elements.prevPageBtn.disabled = !pagination.has_prev;
        }
        if (this.elements.nextPageBtn) {
            this.elements.nextPageBtn.disabled = !pagination.has_next;
        }

        // Generate page numbers
        this.generatePageNumbers(this.currentPage, this.totalPages);
    }

    generatePageNumbers(current, total) {
        if (!this.elements.pageNumbers) return;
        
        this.elements.pageNumbers.innerHTML = '';

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
            this.addPageButton(1);
            if (start > 2) {
                this.addPageDots();
            }
        }

        // Add page numbers
        for (let i = start; i <= end; i++) {
            this.addPageButton(i, i === current);
        }

        // Add dots and last page if needed
        if (end < total) {
            if (end < total - 1) {
                this.addPageDots();
            }
            this.addPageButton(total);
        }
    }

    addPageButton(page, isActive = false) {
        const button = document.createElement('button');
        button.textContent = page;
        button.className = `px-3 py-1 border rounded-md text-sm ${
            isActive 
                ? 'bg-blue-600 text-white border-blue-600' 
                : 'text-gray-500 border-gray-300 hover:bg-gray-50'
        }`;
        
        if (!isActive) {
            button.addEventListener('click', () => {
                this.currentPage = page;
                this.renderCurrentPage();
            });
        }

        this.elements.pageNumbers.appendChild(button);
    }

    addPageDots() {
        const dots = document.createElement('span');
        dots.textContent = '...';
        dots.className = 'px-2 py-1 text-gray-500';
        this.elements.pageNumbers.appendChild(dots);
    }

    updatePeriodInfo(period) {
        if (period && this.elements.periodInfo) {
            this.elements.periodInfo.textContent = `${period.month_name} ${period.year}`;
        }
    }

    // Generic table row builder
    static buildTableRow(item, columns, options = {}) {
        const rowClass = options.rowClass || 'hover:bg-gray-50';
        const cellClass = options.cellClass || 'px-3 py-2 text-sm text-gray-900 border-r border-gray-200';
        
        let html = `<tr class="${rowClass}">`;
        
        columns.forEach((column, index) => {
            const value = TableHelper.formatCellValue(item[column.field], column.type);
            const alignment = column.align || (column.type === 'currency' || column.type === 'number' ? 'text-right' : 'text-left');
            const extraClass = column.class || '';
            const isLast = index === columns.length - 1;
            
            html += `<td class="${cellClass} ${alignment} ${extraClass} ${isLast ? '' : 'border-r border-gray-200'}">`;
            
            if (column.type === 'text' && column.maxWidth) {
                html += `<div class="truncate max-w-${column.maxWidth}" title="${item[column.field]}">${value}</div>`;
            } else {
                html += value;
            }
            
            html += '</td>';
        });
        
        html += '</tr>';
        return html;
    }

    static formatCellValue(value, type) {
        switch (type) {
            case 'currency':
                return TableHelper.formatCurrency(value);
            case 'number':
                return TableHelper.formatNumber(value);
            case 'percent':
                return TableHelper.formatPercent(value);
            default:
                return value || '-';
        }
    }

    // Initialize table with initial data load
    init() {
        this.loadData();
    }
}

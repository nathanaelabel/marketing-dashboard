<!-- Sales Family Section -->
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" x-data="{
    currentLang: localStorage.getItem('language') || 'id',
    translations: {
        title: {
            id: 'Penjualan Per Family (Rp)',
            en: 'Sales Per Family (Rp)'
        }
    },
    init() {
        window.addEventListener('language-changed', (e) => {
            this.currentLang = e.detail.language;
        });
    }
}">
    <div class="p-6 text-gray-900">
        <div class="flex items-center justify-between mb-4">
            <h3 id="family-section-title" class="text-lg font-medium text-gray-900"
                x-text="translations.title[currentLang]">Penjualan Per Family (Rp)</h3>

            <!-- Three-dots Menu -->
            <div class="relative">
                <button type="button" id="sfMenuButton"
                    class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor"
                        viewBox="0 0 24 24">
                        <circle cx="5" cy="12" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="19" cy="12" r="2" />
                    </svg>
                </button>
                <!-- Dropdown Menu -->
                <div id="sfDropdownMenu"
                    class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1" role="menu">
                        <button type="button" id="sfRefreshDataBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh Data
                        </button>
                        <button type="button" id="sfExportExcelBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <i class="bi bi-file-excel text-gray-700"
                                style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                            Export to Excel
                        </button>
                        {{-- <button type="button" id="sfExportPdfBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <i class="bi bi-file-pdf text-gray-700"
                                style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                            Export to PDF
                        </button> --}}
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex-1 min-w-[120px]">
                <label for="family-type-select" class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
                <select id="family-type-select"
                    class="block w-full pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="rp">Rupiah</option>
                    <option value="pcs">Pieces</option>
                </select>
            </div>

            <div class="flex-1 min-w-[120px]">
                <label for="sf-start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <div class="relative">
                    <input type="text" name="start_date" id="sf-start-date" value="{{ $startDate }}"
                        placeholder="Select Date"
                        class="pl-3 pr-8 py-1.5 w-full text-sm rounded-md border border-gray-300 shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd"
                                d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-w-[120px]">
                <label for="sf-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <div class="relative">
                    <input type="text" name="end_date" id="sf-end-date" value="{{ $endDate }}"
                        placeholder="Select Date"
                        class="pl-3 pr-8 py-1.5 w-full text-sm rounded-md border border-gray-300 shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd"
                                d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label for="family-search-input" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="family-search-input" placeholder="Search family name..."
                    class="block w-full pl-3 pr-8 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="family-loading-indicator" class="flex items-center justify-center py-8 hidden">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading table data...</span>
        </div>

        <!-- Error Message -->
        <div id="family-error-message" class="bg-red-50 border border-red-200 rounded-md p-4 mb-4 hidden">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">
                        Error
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p id="family-error-text">An error occurred while loading data.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data Message -->
        <div id="family-no-data-message" class="text-center py-8 text-gray-500 hidden">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Data Available</h3>
            <p class="mt-1 text-sm text-gray-500">No data found for the selected period.</p>
        </div>

        <!-- Table Container -->
        <div id="family-table-container" class="hidden">
            <!-- Period Info and Show Entries -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-blue-800">Show</span>
                        <select id="family-entries-per-page"
                            class="pl-2 pr-6 py-1 text-sm border border-blue-300 rounded bg-white text-blue-800 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="text-sm text-blue-800">entries</span>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-blue-800">
                        <span class="font-medium">Period:</span>
                        <span id="family-period-info">-</span>
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                No.</th>
                            <th
                                class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Family Name</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                TGR</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                BKS</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                JKT</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                PTK</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                LMP</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                BJM</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                CRB</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                BDG</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                MKS</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                SBY</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                SMG</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                PWT</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                DPS</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                PLB</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                PDG</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                MDN</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                PKU</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-blue-50">
                                Nasional</th>
                        </tr>
                    </thead>
                    <tbody id="family-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    <span id="family-pagination-info">-</span>
                </div>
                <div class="flex space-x-2">
                    <button id="family-prev-page"
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        Previous
                    </button>
                    <div id="family-page-numbers" class="flex space-x-1">
                        <!-- Page numbers will be populated by JavaScript -->
                    </div>
                    <button id="family-next-page"
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

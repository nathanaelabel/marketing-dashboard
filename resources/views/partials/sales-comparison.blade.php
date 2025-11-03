<!-- Sales Comparison Section -->
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6 text-gray-900">
        <div class="flex items-center justify-between mb-4">
            <h3 id="sales-comp-section-title" class="text-lg font-medium text-gray-900">Perbandingan Sales, Stok, dan BDP
            </h3>

            <!-- Three-dots Menu -->
            <div class="relative">
                <button type="button" id="scMenuButton"
                    class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor"
                        viewBox="0 0 24 24">
                        <circle cx="5" cy="12" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="19" cy="12" r="2" />
                    </svg>
                </button>
                <!-- Dropdown Menu -->
                <div id="scDropdownMenu"
                    class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1" role="menu">
                        <button type="button" id="scRefreshDataBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh Data
                        </button>
                        <button type="button" id="scExportExcelBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <i class="bi bi-file-excel text-gray-700"
                                style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                            Export to Excel
                        </button>
                        <button type="button" id="scExportPdfBtn"
                            class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                            role="menuitem">
                            <i class="bi bi-file-pdf text-gray-700"
                                style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                            Export to PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex-1 min-w-[200px]">
                <label for="sales-comp-date-select" class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                <input type="date" id="sales-comp-date-select" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"
                    class="block w-full pl-3 pr-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="sales-comp-loading-indicator" class="flex items-center justify-center py-8 hidden">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading table data...</span>
        </div>

        <!-- Error Message -->
        <div id="sales-comp-error-message" class="bg-red-50 border border-red-200 rounded-md p-4 mb-4 hidden">
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
                        <p id="sales-comp-error-text">An error occurred while loading data.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data Message -->
        <div id="sales-comp-no-data-message" class="text-center py-8 text-gray-500 hidden">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Data Available</h3>
            <p class="mt-1 text-sm text-gray-500">No data found for the selected date.</p>
        </div>

        <!-- Table Container -->
        <div id="sales-comp-table-container" class="hidden">
            <!-- Period Info Only (No pagination for all 17 branches) -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <p class="text-sm text-blue-800">
                    <span class="font-medium">Date:</span>
                    <span id="sales-comp-period-info">-</span>
                </p>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th rowspan="2"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                No.</th>
                            <th rowspan="2"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Cabang</th>
                            <th colspan="3"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sales Per <span id="sales-date-header">-</span></th>
                            <th colspan="3"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Stok Per Sales Per <span id="stok-date-header">-</span></th>
                            <th colspan="3"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                BDP Per <span id="bdp-date-header">-</span></th>
                            <th colspan="3"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Stok+BDP Per <span id="stok-bdp-date-header">-</span></th>
                            <th colspan="3"
                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Sales+Stok+BDP Per <span id="total-date-header">-</span></th>
                        </tr>
                        <tr>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Mika</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sparepart</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Total Sales</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Mika</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sparepart</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Total Stok</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Mika</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sparepart</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Total BDP</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Mika</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sparepart</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Total Stok+BDP</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Mika</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-300">
                                Sparepart</th>
                            <th
                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Stok+BDP+Sales</th>
                        </tr>
                    </thead>
                    <tbody id="sales-comp-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

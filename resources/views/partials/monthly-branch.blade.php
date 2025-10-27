<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900">
        <div class="grid grid-cols-1 md:grid-cols-2 items-start mb-4">
            <div class="md:justify-self-start">
                <h3 class="text-2xl font-bold text-gray-900">Monthly Branch Revenue</h3>
            </div>
            <div class="flex items-end space-x-3 md:justify-self-end justify-center mt-3 md:mt-0">
                <!-- Year Range Selector -->
                <div>
                    <label for="monthly-year-select" class="block text-xs font-medium text-gray-500 mb-1">Year Range</label>
                    <select id="monthly-year-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="2025">2024 - 2025</option>
                        <option value="2024">2023 - 2024</option>
                    </select>
                </div>

                <!-- Branch Selector -->
                <div>
                    <label for="monthly-branch-select" class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                    <select id="monthly-branch-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">Loading...</option>
                    </select>
                </div>

                <!-- Category Selector -->
                <div>
                    <label for="monthly-category-select" class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select id="monthly-category-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="MIKA">Mika</option>
                        <option value="SPARE PART">Spare Part</option>
                    </select>
                </div>

                <!-- Three-dots Menu (Horizontal) -->
                <div class="relative">
                    <label class="block text-xs font-medium text-gray-500 mb-1">&nbsp;</label>
                    <button type="button" id="mbMenuButton" class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="mbDropdownMenu" class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" role="menu">
                            <button type="button" id="mbRefreshDataBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh Data
                            </button>
                            <button type="button" id="mbExportExcelBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <i class="bi bi-file-excel text-gray-700" style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to Excel
                            </button>
                            <button type="button" id="mbExportPdfBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <i class="bi bi-file-pdf text-gray-700" style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <canvas id="monthly-branch-chart" style="max-height: 400px; width: 100%;"></canvas>
    </div>
</div>

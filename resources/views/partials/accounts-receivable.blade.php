<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 bg-white rounded-lg shadow-md">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-2xl font-bold text-gray-900">Accounts Receivable</h3>
                <p id="arTotal" class="mt-2 mb-2 text-1xl font-bold text-gray-700">Loading...</p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-right">
                    <p id="arDate" class="text-sm text-gray-500"></p>
                </div>
                <!-- Three-dots Menu (Horizontal) -->
                <div class="relative">
                    <button type="button" id="arMenuButton" class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="arDropdownMenu" class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" role="menu">
                            <button type="button" id="arRefreshDataBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh Data
                            </button>
                            <button type="button" id="arExportExcelBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <i class="bi bi-file-excel text-gray-700" style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to Excel
                            </button>
                            <button type="button" id="arExportPdfBtn" class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap" role="menuitem">
                                <i class="bi bi-file-pdf text-gray-700" style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <canvas id="accountsReceivableChart" data-url="{{ route('accounts-receivable.data') }}" style="max-height: 450px; width: 100%;"></canvas>
        </div>
    </div>
</div>
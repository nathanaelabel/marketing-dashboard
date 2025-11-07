<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg" id="target-revenue-section" x-data="{
    currentLang: localStorage.getItem('language') || 'id',
    translations: {
        title: {
            id: 'Target Penjualan Netto',
            en: 'Target Net Revenue'
        }
    },
    init() {
        window.addEventListener('language-changed', (e) => {
            this.currentLang = e.detail.language;
        });
    }
}">
    <div class="p-6 text-gray-900">
        <div class="grid grid-cols-1 md:grid-cols-2 items-start mb-4">
            <div class="md:justify-self-start">
                <h3 class="text-2xl font-bold text-gray-900" x-text="translations.title[currentLang]">Target Net Revenue
                </h3>
            </div>
            <div class="flex items-end space-x-3 md:justify-self-end justify-center mt-3 md:mt-0">
                <!-- Month Selector -->
                <div>
                    <label for="target-month-select" class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                    <select id="target-month-select"
                        class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9" selected>September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                <!-- Year Selector -->
                <div>
                    <label for="target-year-select" class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                    <select id="target-year-select"
                        class="pl-3 pr-8 py-1.5 w-24 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="2021">2021</option>
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024">2024</option>
                        <option value="2025" selected>2025</option>
                    </select>
                </div>

                <!-- Category Selector -->
                <div>
                    <label for="target-category-select"
                        class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select id="target-category-select"
                        class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="MIKA">Mika</option>
                        <option value="SPARE PART">Spare Part</option>
                    </select>
                </div>

                <!-- Three-dots Menu (Horizontal) -->
                <div class="relative">
                    <label class="block text-xs font-medium text-gray-500 mb-1">&nbsp;</label>
                    <button type="button" id="targetMenuButton"
                        class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor"
                            viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="targetDropdownMenu"
                        class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" role="menu">
                            <button type="button" id="targetRefreshDataBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh Data
                            </button>
                            <button type="button" id="targetExportExcelBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <i class="bi bi-file-excel text-gray-700"
                                    style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to Excel
                            </button>
                            <button type="button" id="targetExportPdfBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <i class="bi bi-file-pdf text-gray-700"
                                    style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Target Button -->
                <div>
                    <button id="edit-target-btn"
                        class="hidden py-1.5 px-2 w-32 text-sm rounded-md border border-blue-300 bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm focus:border-blue-300 focus:ring-1 focus:ring-blue-200 focus:ring-opacity-50 transition duration-200 mt-6 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-edit"></i>
                        <span>Edit Target</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chart Container -->
        <div id="target-chart-container" class="relative">
            <canvas id="target-revenue-chart" style="max-height: 600px; width: 100%;"></canvas>

            <!-- No Targets Message -->
            <div id="no-targets-message" class="hidden text-center p-8">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <i class="fas fa-chart-bar text-gray-400 text-4xl"></i>
                    <div class="text-gray-600">
                        <p class="text-lg font-medium mb-2">Tidak ada data target</p>
                        <p class="text-sm text-gray-500 mb-4">Silakan input target untuk periode <span
                                id="period-text"></span></p>
                    </div>
                    <button id="input-target-btn"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Input Target
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

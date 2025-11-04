<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="px-6 pt-2 pb-6 bg-white rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-bold text-gray-900" x-text="$root.lang === 'id' ? 'Kontribusi Kategori Barang' : 'Category Item Revenue'"></h3>
            <form id="categoryItemDateFilterForm" class="flex items-end space-x-3 pt-2">
                <!-- Type Selector -->
                <div>
                    <label for="categoryItemTypeSelect" class="block text-xs font-medium text-gray-500 mb-1">Type</label>
                    <select id="categoryItemTypeSelect"
                        class="pl-3 pr-8 py-1.5 w-24 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="NETTO">Netto</option>
                        <option value="BRUTO">Bruto</option>
                    </select>
                </div>
                <!-- Start Date Selector -->
                <div>
                    <label for="categoryItemStartDate" class="block text-xs font-medium text-gray-500 mb-1">Start
                        Date</label>
                    <div class="relative">
                        <input type="text" name="start_date" id="categoryItemStartDate" value="{{ $startDate }}"
                            placeholder="Select Date"
                            class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
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
                {{-- <div class="flex items-center h-8 text-gray-400">
                    <span class="text-xs">to</span>
                </div> --}}
                <!-- End Date Selector -->
                <div>
                    <label for="categoryItemEndDate" class="block text-xs font-medium text-gray-500 mb-1">End
                        Date</label>
                    <div class="relative">
                        <input type="text" name="end_date" id="categoryItemEndDate" value="{{ $endDate }}"
                            placeholder="Select Date"
                            class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
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
                <!-- Three-dots Menu (Horizontal) -->
                <div class="relative">
                    <label class="block text-xs font-medium text-gray-500 mb-1">&nbsp;</label>
                    <button type="button" id="ciMenuButton"
                        class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="currentColor"
                            viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="2" />
                            <circle cx="12" cy="12" r="2" />
                            <circle cx="19" cy="12" r="2" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div id="ciDropdownMenu"
                        class="hidden absolute right-0 mt-2 min-w-max rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1" role="menu">
                            <button type="button" id="ciRefreshDataBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-700"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh Data
                            </button>
                            <button type="button" id="ciExportExcelBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <i class="bi bi-file-excel text-gray-700"
                                    style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to Excel
                            </button>
                            <button type="button" id="ciExportPdfBtn"
                                class="flex items-center w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                                role="menuitem">
                                <i class="bi bi-file-pdf text-gray-700"
                                    style="font-size: 1.25rem; margin-right: 0.75rem;"></i>
                                Export to PDF
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div id="category-item-chart-container" class="mt-4" style="position: relative; height: 450px; width: 100%;">
            <canvas id="categoryItemChart" data-url="{{ route('category-item.data') }}"></canvas>
        </div>
        <div id="category-item-message" class="text-center text-gray-500 py-4"></div>
        <div class="flex justify-center items-center mt-4 space-x-2">
            <button id="ci-prev-page"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                Previous
            </button>
            <button id="ci-next-page"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>
</div>

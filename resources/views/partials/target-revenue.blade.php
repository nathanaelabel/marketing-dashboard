<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg" id="target-revenue-section">
    <div class="p-6 text-gray-900">
        <div class="grid grid-cols-1 md:grid-cols-2 items-start mb-4">
            <div class="md:justify-self-start">
                <h3 class="text-2xl font-bold text-gray-900">Target Net Revenue</h3>
            </div>
            <div class="flex items-end space-x-3 md:justify-self-end justify-center mt-3 md:mt-0">
                <!-- Month Selector -->
                <div>
                    <label for="target-month-select" class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                    <select id="target-month-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
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
                    <select id="target-year-select" class="pl-3 pr-8 py-1.5 w-24 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="2021">2021</option>
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024">2024</option>
                        <option value="2025" selected>2025</option>
                    </select>
                </div>

                <!-- Category Selector -->
                <div>
                    <label for="target-category-select" class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select id="target-category-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="MIKA">Mika</option>
                        <option value="SPARE PART">Spare Part</option>
                    </select>
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
                        <p class="text-lg font-medium mb-2">Target belum diinput</p>
                        <p class="text-sm text-gray-500 mb-4">Silakan input target untuk periode <span id="period-text"></span></p>
                    </div>
                    <button id="input-target-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Input Target
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

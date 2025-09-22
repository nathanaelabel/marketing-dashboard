<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <label for="location-filter" class="block text-sm font-medium text-gray-700">Location</label>
            <select id="location-filter" name="location" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option>National</option>
                <!-- Locations will be populated by JS -->
            </select>
        </div>
        <div>
            <label for="start-date-filter" class="block text-sm font-medium text-gray-700">Start Date</label>
            <div class="relative mt-1">
                <input type="text" id="start-date-filter" name="start_date" class="flatpickr-input block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" placeholder="Select start date">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-400">
                        <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
        </div>
        <div>
            <label for="end-date-filter" class="block text-sm font-medium text-gray-700">End Date</label>
            <div class="relative mt-1">
                <input type="text" id="end-date-filter" name="end_date" class="flatpickr-input block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" placeholder="Select end date">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-400">
                        <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics & Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Metric Cards -->
        <div class="lg:col-span-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-6">
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="total-so-label">Total Sales Order</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-900" id="total-so-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="pending-so-label">Pending Sales Order</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-900" id="pending-so-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="store-returns-label">Store Returns</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-900" id="store-returns-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="stock-value-label">Stock Value</h3>
                <p class="mt-1 text-2xl font-semibold text-gray-900" id="stock-value-value">-</p>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="lg:col-span-2 bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-medium text-gray-500">Accounts Receivable</h3>
            <p class="text-2xl font-bold text-gray-900" id="ar-pie-total">-</p>
            <div class="h-96 mt-4">
                <canvas id="ar-pie-chart"></canvas>
            </div>
        </div>
    </div>
</div>
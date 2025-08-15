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
            <label for="start-date-filter" class="block text-sm font-medium text-gray-700">Start date</label>
            <input type="text" id="start-date-filter" name="start_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Select start date">
        </div>
        <div>
            <label for="end-date-filter" class="block text-sm font-medium text-gray-700">End date</label>
            <input type="text" id="end-date-filter" name="end_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Select end date">
        </div>
    </div>

    <!-- Metrics & Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Metric Cards -->
        <div class="lg:col-span-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-6">
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="total-so-label">Total Sales Order</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900" id="total-so-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="pending-so-label">Pending Sales Order</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900" id="pending-so-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="stock-value-label">Stock Value</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900" id="stock-value-value">-</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500" id="store-returns-label">Store Returns</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900" id="store-returns-value">-</p>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="lg:col-span-2 bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-900">Accounts Receivable</h3>
            <p class="text-3xl font-bold text-gray-900" id="ar-pie-total">-</p>
            <div class="h-96 mt-4">
                <canvas id="ar-pie-chart"></canvas>
            </div>
        </div>
    </div>
</div>
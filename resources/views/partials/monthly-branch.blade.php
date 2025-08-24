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
            </div>
        </div>
        <canvas id="monthly-branch-chart" style="max-height: 400px; width: 100%;"></canvas>
    </div>
</div>
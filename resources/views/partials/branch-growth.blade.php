<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900">
        <div class="grid grid-cols-1 md:grid-cols-2 items-start mb-4">
            <div class="md:justify-self-start">
                <h3 class="text-2xl font-bold text-gray-900">Branch Revenue Growth</h3>
            </div>
            <div class="flex items-end space-x-3 md:justify-self-end justify-center mt-3 md:mt-0">
                <!-- Start Year Selector -->
                <div>
                    <label for="growth-start-year-select" class="block text-xs font-medium text-gray-500 mb-1">Start year</label>
                    <select id="growth-start-year-select" class="pl-3 pr-8 py-1.5 w-24 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="2021">2021</option>
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024" selected>2024</option>
                    </select>
                </div>

                <!-- End Year Selector -->
                <div>
                    <label for="growth-end-year-select" class="block text-xs font-medium text-gray-500 mb-1">End year</label>
                    <select id="growth-end-year-select" class="pl-3 pr-8 py-1.5 w-24 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024">2024</option>
                        <option value="2025" selected>2025</option>
                    </select>
                </div>

                <!-- Branch Selector -->
                <div>
                    <label for="growth-branch-select" class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                    <select id="growth-branch-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">Loading...</option>
                    </select>
                </div>

                <!-- Category Selector -->
                <div>
                    <label for="growth-category-select" class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select id="growth-category-select" class="pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="MIKA">Mika</option>
                        <option value="SPARE PART">Spare Part</option>
                    </select>
                </div>
            </div>
        </div>
        <canvas id="branch-growth-chart" style="max-height: 400px; width: 100%;"></canvas>
    </div>
</div>
<div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 bg-white rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-900">Category Item Revenue</h3>
            <form id="category-item-form" class="flex items-center space-x-2">
                <div class="relative">
                    <label for="ci_start_date" class="text-sm font-medium text-gray-600">Start date</label>
                    <input type="text" name="start_date" id="ci_start_date" placeholder="Select Date" class="flatpickr-input pl-3 pr-8 py-1.5 w-36 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="relative">
                    <label for="ci_end_date" class="text-sm font-medium text-gray-600">End date</label>
                    <input type="text" name="end_date" id="ci_end_date" placeholder="Select Date" class="flatpickr-input pl-3 pr-8 py-1.5 w-36 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </form>
        </div>
        <div id="category-item-chart-container" style="position: relative; height: 500px; width: 100%;">
            <canvas id="categoryItemChart" data-url="{{ route('category-item.data') }}"></canvas>
        </div>
    </div>
</div>
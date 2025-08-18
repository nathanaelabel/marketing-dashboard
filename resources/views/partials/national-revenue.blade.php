<div class="p-6 text-gray-900">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">National Revenue</h3>
            <p id="nationalTotalRevenueDisplay" class="mt-2 mb-2 text-1xl font-medium text-gray-700">Rp 0</p> <!-- Updated by JS -->
        </div>
        <form id="dateFilterForm" class="flex items-end space-x-3">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                <div class="relative">
                    <input type="text" name="start_date" id="start_date" value="{{ $startDate }}" placeholder="Select Date" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="flex items-center h-8 text-gray-400">
                <span class="text-xs">to</span>
            </div>
            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
                <div class="relative">
                    <input type="text" name="end_date" id="end_date" value="{{ $endDate }}" placeholder="Select Date" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <canvas id="revenueChart" data-url="{{ route('national-revenue.data') }}" style="max-height: 400px; width: 100%;"></canvas>
</div>
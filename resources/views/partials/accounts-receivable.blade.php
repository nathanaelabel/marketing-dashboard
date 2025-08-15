<div class="p-6 bg-white rounded-lg shadow-md">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">Accounts Receivable</h3>
            <p id="arTotal" class="mt-2 mb-2 text-1xl font-medium text-gray-700">Loading...</p>
        </div>
        <div class="text-right">
            <p id="arDate" class="text-sm text-gray-500"></p>
        </div>
    </div>
    <div style="height: 400px;">
        <canvas id="accountsReceivableChart" data-url="{{ route('accounts-receivable.data') }}"></canvas>
    </div>
</div>
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('National Revenue') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900">Total Revenue</h3>
                    <p class="mt-1 text-3xl font-semibold text-gray-700">Rp {{ number_format($totalRevenue / 1000000000, 2) }}B</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">National Revenue</h3>
                        <form method="GET" action="{{ route('dashboard') }}" class="flex items-end space-x-3">
                            <div class="relative">
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <div class="relative">
                                    <input type="text" name="start_date" id="start_date" value="{{ $startDate }}" class="pl-10 pr-3 py-2 w-40 text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center h-8 text-gray-500">
                                <span>to</span>
                            </div>
                            <div class="relative">
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <div class="relative">
                                    <input type="text" name="end_date" id="end_date" value="{{ $endDate }}" class="pl-10 pr-3 py-2 w-40 text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="h-8 flex items-end">
                                <button type="submit" class="px-4 py-2 h-10 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Filter</button>
                        </form>
                    </div>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        flatpickr('#start_date', {
            dateFormat: 'Y-m-d',
        });
        flatpickr('#end_date', {
            dateFormat: 'Y-m-d',
        });

        const ctx = document.getElementById('revenueChart');
        const branchRevenue = @json($branchRevenue);

        Chart.register(ChartDataLabels);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(branchRevenue),
                datasets: [{
                    label: 'Revenue',
                    data: Object.values(branchRevenue),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: false, // The title is already in the card header
                    },
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        formatter: (value, context) => {
                            return Math.round(value / 1000000);
                        },
                        font: {
                            weight: 'bold'
                        },
                        color: '#444'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'in million Rupiah (Rp)'
                        },
                        ticks: {
                            stepSize: 5000000,
                            callback: function(value, index, values) {
                                return value / 1000000;
                            }
                        }
                    },
                    x: {
                         title: {
                            display: false, // X-axis title is self-explanatory
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
</x-app-layout>

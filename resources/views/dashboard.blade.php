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
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">National Revenue</h3>
                            <p class="mt-1 text-2xl font-semibold text-blue-600">Rp {{ number_format($totalRevenue / 1000000000, 2) }}B</p>
                        </div>
                        <form id="dateFilterForm" method="GET" action="{{ route('dashboard') }}" class="flex items-end space-x-3">
                            <div>
                                <label for="start_date" class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                                <div class="relative">
                                    <input type="text" name="start_date" id="start_date" value="{{ $startDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-400">
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
                                    <input type="text" name="end_date" id="end_date" value="{{ $endDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-400">
                                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('.flatpickr-input', {
                dateFormat: "Y-m-d",
                onChange: function(selectedDates, dateStr, instance) {
                    document.getElementById('dateFilterForm').submit();
                }
            });

            const ctx = document.getElementById('revenueChart');
            const branchRevenue = @json($branchRevenue);

            const dataValues = Object.values(branchRevenue);
            const maxValue = dataValues.length > 0 ? Math.max(...dataValues) : 0;
            const suggestedMax = maxValue * 1.2;

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
                                        label += new Intl.NumberFormat('id-ID', {
                                            minimumFractionDigits: 0
                                        }).format(context.parsed.y);
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
                            suggestedMax: suggestedMax,
                            title: {
                                display: true,
                                text: 'Million Rupiah (Rp)'
                            },
                            ticks: {
                                callback: function(value, index, values) {
                                    return value / 1000000;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: false,
                            }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
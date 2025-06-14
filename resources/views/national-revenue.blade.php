<div class="p-6 text-gray-900">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-2xl font-bold text-gray-900">National Revenue</h3>
            <p class="mt-2 mb-2 text-1xl font-medium text-gray-700">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        </div>
        <form id="dateFilterForm" method="GET" action="{{ route('dashboard') }}" class="flex items-end space-x-3">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
                <div class="relative">
                    <input type="text" name="start_date" id="start_date" value="{{ $startDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
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
                    <input type="text" name="end_date" id="end_date" value="{{ $endDate }}" class="flatpickr-input pl-3 pr-8 py-1.5 w-32 text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50">
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-gray-600">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <canvas id="revenueChart"></canvas>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('.flatpickr-input', {
            altInput: true,
            altFormat: "d-m-Y",
            dateFormat: "Y-m-d",
            onChange: function(selectedDates, dateStr, instance) {
                document.getElementById('dateFilterForm').submit();
            }
        });

        const ctx = document.getElementById('revenueChart');
        const branchRevenue = @json($branchRevenue);

        const dataValues = Object.values(branchRevenue);
        const SBY = branchRevenue['SBY'] || 0;
        const JKT = branchRevenue['JKT'] || 0;
        const BDG = branchRevenue['BDG'] || 0;
        const totalRevenueForAvg = SBY + JKT + BDG;
        const numberOfBranchesForAvg = (SBY > 0 ? 1 : 0) + (JKT > 0 ? 1 : 0) + (BDG > 0 ? 1 : 0);
        const averageRevenue = numberOfBranchesForAvg > 0 ? totalRevenueForAvg / numberOfBranchesForAvg : 0;

        const useBillions = averageRevenue >= 1000000000;
        const yAxisLabel = useBillions ? 'Billion Rupiah (Rp)' : 'Million Rupiah (Rp)';
        const divisor = useBillions ? 1000000000 : 1000000;

        const maxValue = dataValues.length > 0 ? Math.max(...dataValues) : 0;

        // Ensure some default max if all data is 0
        const suggestedMax = maxValue > 0 ? (maxValue / divisor) * 1.2 * divisor : (useBillions ? 1000000000 : 1000000);

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
                        display: false,
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
                            return Math.round(value / divisor);
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
                            text: yAxisLabel,
                            padding: {
                                top: 0,
                                left: 0,
                                right: 0,
                                bottom: 20
                            }
                        },
                        ticks: {
                            callback: function(value, index, values) {
                                return value / divisor;
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
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('National Revenue') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('national-revenue')
            </div>

            <div class="mt-10 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('accounts-receivable')
            </div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
            @endpush

        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('.flatpickr-input', {
                altInput: true,
                altFormat: "d-m-Y", // Format for the visible input
                dateFormat: "Y-m-d", // Format for the hidden input (submitted to server)
                onChange: function(selectedDates, dateStr, instance) {
                    document.getElementById('dateFilterForm').submit();
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
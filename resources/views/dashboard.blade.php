<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.national-revenue')
            @include('partials.accounts-receivable')
            @include('partials.sales-metrics')
            @include('partials.category-item')
            @include('partials.national-yearly')
            @include('partials.monthly-branch')
            @include('partials.branch-growth')
            @include('partials.target-revenue')

            <div id="js-data"
                data-locations-url="{{ route('sales-metrics.locations') }}"
                data-sales-metrics-url="{{ route('sales-metrics.data') }}"></div>
            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/flatpickr?v=1.1"></script>

            <script src="{{ asset('js/dashboard/chart-helper.js') }}"></script>
            <script src="{{ asset('js/dashboard/national-revenue.js') }}"></script>
            <script src="{{ asset('js/dashboard/sales-metrics.js') }}"></script>
            <script src="{{ asset('js/dashboard/national-yearly.js') }}"></script>
            <script src="{{ asset('js/dashboard/monthly-branch.js') }}"></script>
            <script src="{{ asset('js/dashboard/branch-growth.js') }}"></script>
            <script src="{{ asset('js/dashboard/target-revenue.js') }}"></script>
            @endpush

        </div>
    </div>

</x-app-layout>
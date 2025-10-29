<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('partials.sales-item')
            @include('partials.sales-family')

            @push('scripts')
                <script src="{{ asset('js/dashboard/table-helper.js') }}"></script>
                <script src="{{ asset('js/dashboard/sales-item.js') }}"></script>
                <script src="{{ asset('js/dashboard/sales-family.js') }}"></script>
            @endpush

        </div>
    </div>

</x-app-layout>

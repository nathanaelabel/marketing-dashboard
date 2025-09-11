<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Input Target</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center text-gray-600 hover:text-gray-900">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Header -->
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                                {{ $isEditMode ? 'Edit Target Revenue' : 'Input Target Revenue' }}
                            </h2>
                            <p class="text-gray-600">
                                Period: <span class="font-medium">
                                    {{
                                        collect([
                                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                        ])[$month] 
                                    }} {{ $year }} - {{ ucwords(strtolower($category)) }}
                                </span>
                            </p>
                            @if($isEditMode)
                            <p class="text-sm text-blue-600 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                You are editing existing targets. Make your changes and save to update.
                            </p>
                            @endif
                        </div>

                        <!-- Alert Messages -->
                        <div id="alert-container"></div>

                        <!-- Form -->
                        <form id="target-form" class="space-y-6">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month }}">
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="category" value="{{ $category }}">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @foreach($branches as $branch)
                                <div class="space-y-2">
                                    <label for="target_{{ str_replace(' ', '_', $branch) }}" class="block text-sm font-medium text-gray-700">
                                        Target {{ $branch }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">Rp</span>
                                        <input
                                            type="text"
                                            id="target_{{ str_replace(' ', '_', $branch) }}"
                                            name="targets[{{ $branch }}]"
                                            value="{{ $existingTargets[$branch] ?? '' }}"
                                            class="pl-10 pr-3 py-2 w-full text-sm rounded-md border border-gray-300 shadow-sm focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200 focus:ring-opacity-50"
                                            placeholder="0"
                                            inputmode="numeric"
                                            required>
                                    </div>
                                    <div class="text-xs text-red-600 hidden error-message" id="error_{{ str_replace(' ', '_', $branch) }}"></div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <a href="{{ route('dashboard') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Cancel
                                </a>
                                <button
                                    type="submit"
                                    id="submit-btn"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span id="submit-text">{{ $isEditMode ? 'Update' : 'Save' }}</span>
                                    <i id="submit-spinner" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('js/dashboard/branch-target-input.js') }}"></script>
</body>

</html>
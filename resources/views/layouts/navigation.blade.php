<nav x-data="{
    open: false,
    languageOpen: false,
    currentLanguage: localStorage.getItem('language') || 'id',
    languages: {
        'id': { name: 'Bahasa', flag: 'id' },
        'en': { name: 'English', flag: 'gb' }
    },
    setLanguage(lang) {
        this.currentLanguage = lang;
        localStorage.setItem('language', lang);
        this.languageOpen = false;
        window.dispatchEvent(new CustomEvent('language-changed', { detail: { language: lang } }));
    }
}" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <img src="{{ asset('images/logowin.png') }}" alt="Puri Wira Mahkota Logo"
                            class="block h-9 w-auto mr-2" />
                        <span class="text-xl font-semibold text-gray-800">Puri Wira Mahkota</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('report')" :active="request()->routeIs('report')">
                        {{ __('Report') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings and Language Dropdowns -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 space-x-3">
                <!-- Language Selector -->
                <div class="relative" @click.away="languageOpen = false">
                    <button @click="languageOpen = !languageOpen"
                        class="inline-flex items-center px-3 py-2 text-sm leading-4 font-medium rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-0 focus:ring-indigo-500 transition ease-in-out duration-150">
                        <span :class="'fi fi-' + languages[currentLanguage].flag + ' bg-gray-100 p-0.5 rounded'"
                            class="mr-2"></span>
                        <span x-text="languages[currentLanguage].name"></span>
                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="languageOpen" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                        style="display: none;">
                        <div class="py-1">
                            <template x-for="(lang, code) in languages" :key="code">
                                <button @click="setLanguage(code)"
                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center"
                                    :class="{ 'bg-gray-50': currentLanguage === code }">
                                    <span :class="'fi fi-' + lang.flag + ' bg-gray-100 p-0.5 rounded'"
                                        class="mr-3"></span>
                                    <span x-text="lang.name"></span>
                                    <svg x-show="currentLanguage === code" class="ml-auto h-5 w-5 text-indigo-600"
                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('report')" :active="request()->routeIs('report')">
                {{ __('Report') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Language Selector -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="text-xs font-medium text-gray-500 mb-2">Language</div>
                <div class="space-y-1">
                    <template x-for="(lang, code) in languages" :key="code">
                        <button @click="setLanguage(code)"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
                            :class="{ 'bg-gray-100': currentLanguage === code }">
                            <span :class="'fi fi-' + lang.flag" class="mr-3"></span>
                            <span x-text="lang.name"></span>
                            <svg x-show="currentLanguage === code" class="ml-auto h-5 w-5 text-indigo-600"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel Debug Monitor') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tailwind Config -->
    <script>
    tailwind.config = {
        darkMode: 'class',
    };
    </script>
</head>

<body class="w-full h-full bg-gray-100 dark:bg-gray-900 text-black dark:text-white">
    <main class="">

        <header class="pt-6 pb-4 sm:pb-6 bg-gray-200 dark:bg-gray-800 w-full z-[999] fixed">
            <div class="mx-auto flex flex-wrap items-center gap-10 px-4 sm:flex-nowrap sm:px-6 lg:px-8">
                <h1 class="font-semibold text-indigo-700 dark:text-white-900 text-3xl">Debug Monitor <span
                        class="text-gray-900 dark:text-white">- Laravel</span></h1>
                <div
                    class="flex w-full gap-x-8 font-semibold sm:order-0 sm:w-auto sm:border-l sm:border-gray-200 sm:pl-6 dark:sm:border-white/10">
                    <a href="{{ route('debug-monitor.dashboard') }}"
                        class="{{ request()->routeIs('debug-monitor.dashboard') ? 'text-indigo-600 dark:text-indigo-700' : 'text-gray-700 dark:text-white' }}">Dashboard</a>
                    <a href="{{ route('debug-monitor.rules.index') }}"
                        class="{{ request()->routeIs('debug-monitor.rules.*') ? 'text-indigo-600 dark:text-indigo-700' : 'text-gray-700 dark:text-white' }}">Rules</a>
                </div>

                @if( ! request()->routeIs('debug-monitor.rules.create'))
                <a href="{{ route('debug-monitor.rules.create') }}"
                    class="ml-auto flex items-center gap-x-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-600 dark:shadow-none dark:hover:bg-indigo-700 dark:focus-visible:outline-indigo-500">
                    <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true"
                        class="-ml-1.5 size-5">
                        <path
                            d="M10.75 6.75a.75.75 0 0 0-1.5 0v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5Z" />
                    </svg>
                    New Rule
                </a>
                @endif
                <button id="darkModeToggle"
                    class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white transition-colors duration-300">
                    <!-- Sun icon for light mode -->
                    <svg id="sunIcon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <!-- Moon icon for dark mode -->
                    <svg id="moonIcon" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9 9 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>
        </header>
        <div class="relative isolate overflow-hidden  w-full px-6 mx-auto">
            <div class="mx-auto px-4 sm:px-6 lg:px-8 mt-25">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-200 border border-red-400 text-red-800 px-4 py-3 rounded">
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            @yield('content')
        </div>
    </main>
    @stack('scripts')

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const html = document.documentElement;
            const toggle = document.getElementById('darkModeToggle');
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');

            // Initialize theme from localStorage or system preference
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)')
                .matches)) {
                html.classList.add('dark');
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            } else {
                html.classList.remove('dark');
                sunIcon.classList.remove('hidden');
                moonIcon.classList.add('hidden');
            }

            // Toggle theme
            toggle.addEventListener('click', () => {
                html.classList.toggle('dark');
                const isDark = html.classList.contains('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                sunIcon.classList.toggle('hidden', isDark);
                moonIcon.classList.toggle('hidden', !isDark);
            });
        });
    </script>

</body>

</html>

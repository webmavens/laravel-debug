<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100 dark:bg-gray-900">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel Debug Monitor') }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap"
        rel="stylesheet">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js (Required for Mobile Sidebar Toggle) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: {
                    figtree: ['Figtree', 'sans-serif'],
                },
            },
        },
    }
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 font-figtree h-full w-full">
    <div class="bg-gray-100 dark:bg-gray-900 w-full font-figtree" x-data="{ sidebarOpen: false }">
        @include('debug-monitor::layouts.sidebar')

        <div class="flex flex-col lg:pl-72 min-h-screen">
            @include('debug-monitor::layouts.header')

            <main class="flex-1 py-10 pt-24 sm:pt-28">
                <div class="px-4 sm:px-6 lg:px-8 max-w-[1440px] mx-auto">
                    
                    @if(session('success'))
                    <div class="rounded-md bg-green-50 p-4 dark:bg-green-500/10 dark:outline dark:outline-green-500/20 mb-5">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true"
                                    class="size-5 text-green-400">
                                    <path
                                        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
                                        clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800 dark:text-green-300">
                                    {{ session('success') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(session('error'))
                    <div class="rounded-md bg-red-50 p-4 dark:bg-red-500/10 dark:outline dark:outline-red-500/20 mb-5">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true"
                                    class="size-5 text-red-400">
                                    <path
                                        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Zm0 7.5a.75.75 0 1 0-1.5 0 .75.75 0 0 0 1.5 0Z"
                                        clip-rule="evenodd" fill-rule="evenodd" />
                                </svg>
                            </div>

                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                    {{ session('error') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

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

        document.addEventListener("DOMContentLoaded", function () {
            document.addEventListener("submit", function (e) {
                const form = e.target;

                if (!(form instanceof HTMLFormElement)) return;

                // Get the button that triggered submit
                const btn = e.submitter;

                if (!btn) return;

                btn.disabled = true;

                const originalText = btn.innerText;
                btn.dataset.originalText = originalText;

                btn.innerText = "Processing...";
                btn.classList.add("opacity-50", "cursor-not-allowed");
            });
        });

    </script>
    @stack('scripts')

</body>

</html>

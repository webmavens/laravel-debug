<!DOCTYPE html>
<html lang="en" class="h-full bg-white">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel Debug Monitor') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="h-full">
    <main>
        <div class="relative isolate overflow-hidden">
            <header class="pt-6 pb-4 sm:pb-6 bg-gray-100 w-full z-[999] fixed">
                <div class="mx-auto flex flex-wrap items-center gap-6 px-4 sm:flex-nowrap sm:px-6 lg:px-8">
                    <h1 class="text-base/7 font-semibold text-gray-900 dark:text-white">Debug Monitor</h1>
                    <div
                        class="order-last flex w-full gap-x-8 text-sm/6 font-semibold sm:order-0 sm:w-auto sm:border-l sm:border-gray-200 sm:pl-6 sm:text-sm/7 dark:sm:border-white/10">
                        <a href="{{ route('debug-monitor.dashboard') }}"
                            class="{{ request()->routeIs('debug-monitor.dashboard') ? 'text-indigo-600 dark:text-indigo-700' : 'text-gray-700 dark:text-gray-300' }}">Dashboard</a>
                        <a href="{{ route('debug-monitor.rules.index') }}"
                            class="{{ request()->routeIs('debug-monitor.rules.*') ? 'text-indigo-600 dark:text-indigo-700' : 'text-gray-700 dark:text-gray-300' }}">Rules</a>
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
                </div>
            </header>

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
</body>

</html>
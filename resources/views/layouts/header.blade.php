
<header class="border-b dark:border-white/10 bg-gray-100 dark:bg-gray-900 pt-4 pb-4 sm:pt-6 sm:pb-6 fixed top-0 z-40 w-full lg:left-72 lg:w-[calc(100%-18rem)]">
    <div class="max-w-[1440px] mx-auto flex flex-wrap items-center gap-4 sm:flex-nowrap px-4 sm:px-6 lg:px-8 2xl:px-6">
        
        <!-- Mobile Sidebar Toggle button -->
        <button type="button" 
                @click="sidebarOpen = true" 
                class="-m-2.5 p-2.5 text-gray-700 lg:hidden dark:text-gray-400 mr-2">
            <span class="sr-only">Open sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <!-- Actions -->
        <div class="ml-auto flex items-center gap-3 sm:gap-5">
            @if( ! request()->routeIs('debug-monitor.rules.create'))
            <a href="{{ route('debug-monitor.rules.create') }}"
                class="flex items-center gap-x-1 rounded-md bg-[#7746ec] px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-600 dark:shadow-none dark:hover:bg-indigo-700 dark:focus-visible:outline-indigo-500 whitespace-nowrap">
                <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="-ml-1.5 size-5 hidden sm:block">
                    <path
                        d="M10.75 6.75a.75.75 0 0 0-1.5 0v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5Z">
                    </path>
                </svg>
                New Rule
            </a>
            @endif
            
            <button id="darkModeToggle"
                class="p-2 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white transition-colors duration-300 shrink-0">
                <!-- Sun icon for light mode -->
                <svg id="sunIcon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                <!-- Moon icon for dark mode -->
                <svg id="moonIcon" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9 9 0 008.354-5.646z"></path>
                </svg>
            </button>
        </div>
    </div>
</header>

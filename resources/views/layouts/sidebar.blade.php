<div x-show="sidebarOpen" style="display: none;" class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900/80"></div>

    <div class="fixed inset-0 flex">
        <!-- Sidebar Panel -->
        <div x-show="sidebarOpen" @click.away="sidebarOpen = false"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full" class="relative mr-16 flex w-full max-w-[80dvw] flex-1">

            <!-- Close Button -->
            <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                <button type="button" @click="sidebarOpen = false" class="-m-2.5 p-2.5">
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- MOBILE NAV CONTENT -->
            <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4 dark:bg-gray-900">
                <nav class="relative flex flex-1 flex-col mt-6">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                        <!-- Logo / Title -->
                        <div class="flex gap-2 items-center">
                            <svg class="w-5 lg:h-5 text-[#8b5cf6]" fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path
                                    d="M10 1a4 4 0 0 0-4 4h8a4 4 0 0 0-4-4zm9 9h-3V7.503c0-.028-.007-.053-.008-.08l2.215-2.216a1 1 0 1 0-1.414-1.414l-2.215 2.215c-.028-.001-.053-.008-.08-.008H5.502c-.028 0-.053.007-.08.008L3.206 3.793a1 1 0 1 0-1.414 1.414l2.215 2.215c0 .028-.007.053-.007.081V10H1a1 1 0 0 0 0 2h3c0 .78.156 1.52.427 2.204-.044.031-.094.05-.134.089L1.464 17.12a1 1 0 0 0 1.415 1.415l2.601-2.602A5.995 5.995 0 0 0 9 17.91V8h2v9.91a5.995 5.995 0 0 0 3.52-1.976l2.601 2.602a1 1 0 0 0 1.415-1.415l-2.829-2.828c-.04-.04-.09-.058-.134-.09A5.956 5.956 0 0 0 16 12h3a1 1 0 0 0 0-2z">
                                </path>
                            </svg>
                            <h1 class="text-lg text-[#374151] dark:text-white font-medium truncate">
                                <span class="font-semibold">Debug Monitor Laravel
                            </h1>
                        </div>
                        <li>
                            <ul role="list" class="-mx-2 space-y-1 mt-3">
                                <li>
                                    <a href="{{ route('debug-monitor.dashboard') }}"
                                        class="group flex items-center gap-x-3 rounded-md px-3 py-2 text-base/6 font-normal
                                        {{ request()->routeIs('debug-monitor.dashboard')
                                            ? 'bg-gray-200 text-[#7746ec] dark:bg-[#1f2937] dark:text-[#a78bfa]'
                                            : 'text-gray-700 hover:bg-gray-200 hover:text-[#7746ec] dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-[#8b5cf6]' }}">
                                        <svg class="h-[20px] w-[20px] shrink-0 text-[#7746ec] dark:text-[#8b5cf6] group-hover:text-[#7746ec] dark:group-hover:text-[#8b5cf6]"
                                            fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm9-9A2.25 2.25 0 0011 4.25v2.5A2.25 2.25 0 0013.25 9h2.5A2.25 2.25 0 0018 6.75v-2.5A2.25 2.25 0 0015.75 2h-2.5zm0 9A2.25 2.25 0 0011 13.25v2.5A2.25 2.25 0 0013.25 18h2.5A2.25 2.25 0 0018 15.75v-2.5A2.25 2.25 0 0015.75 11h-2.5z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        Dashboard
                                    </a>
                                </li>

                                <li>
                                    <a href="{{ route('debug-monitor.rules.index') }}"
                                        class="group flex items-center gap-x-3 rounded-md px-3 py-2 text-base/6 font-normal
                                            {{ request()->routeIs('debug-monitor.rules.*')
                                                ? 'bg-gray-200 text-[#7746ec] dark:bg-[#1f2937] dark:text-[#a78bfa]'
                                                : 'text-gray-700 hover:bg-gray-200 hover:text-[#7746ec] dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-[#8b5cf6]' }}">

                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                            data-slot="icon" aria-hidden="true" class="size-6 shrink-0 text-gray-400">
                                            <path
                                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Rules
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <div class="text-xs/6 font-semibold text-gray-400">Recent Errors</div>
                            <ul role="list" class="-mx-2 mt-2 space-y-1">
                                @forelse($recentErrors as $error)
                                <li>
                                    <a href="{{ route('debug-monitor.rules.show', $error->debug_rule_id) }}"
                                        class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-200 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-[0.625rem] font-medium text-gray-400 group-hover:border-indigo-600 group-hover:text-indigo-600 dark:border-white/15 dark:bg-white/5 dark:group-hover:border-white/20 dark:group-hover:text-white">E</span>
                                        <span class="truncate">{{ $error->rule->name }}</span>
                                    </a>
                                </li>
                                @empty
                                <li> No errors found. </li>
                                @endforelse
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- DESKTOP SIDEBAR -static-->
<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
    <div
        class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4 dark:border-gray-800 dark:bg-gray-900">
        <nav class="relative flex flex-1 flex-col mt-5">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <!-- Logo / Title -->
                <div class="flex gap-2 items-center">
                    <svg class="w-4 h-4 lg:w-6 lg:h-6 text-[#8b5cf6]" fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path
                            d="M10 1a4 4 0 0 0-4 4h8a4 4 0 0 0-4-4zm9 9h-3V7.503c0-.028-.007-.053-.008-.08l2.215-2.216a1 1 0 1 0-1.414-1.414l-2.215 2.215c-.028-.001-.053-.008-.08-.008H5.502c-.028 0-.053.007-.08.008L3.206 3.793a1 1 0 1 0-1.414 1.414l2.215 2.215c0 .028-.007.053-.007.081V10H1a1 1 0 0 0 0 2h3c0 .78.156 1.52.427 2.204-.044.031-.094.05-.134.089L1.464 17.12a1 1 0 0 0 1.415 1.415l2.601-2.602A5.995 5.995 0 0 0 9 17.91V8h2v9.91a5.995 5.995 0 0 0 3.52-1.976l2.601 2.602a1 1 0 0 0 1.415-1.415l-2.829-2.828c-.04-.04-.09-.058-.134-.09A5.956 5.956 0 0 0 16 12h3a1 1 0 0 0 0-2z">
                        </path>
                    </svg>
                    <h1 class="text-md sm:text-xl text-[#374151] dark:text-white font-medium truncate">
                        <span class="font-semibold">Debug Monitor Laravel
                    </h1>
                </div>

                <li>
                    <ul role="list" class="-mx-2 space-y-1 mt-3">
                        <li>
                            <a href="{{ route('debug-monitor.dashboard') }}"
                                class="group flex items-center gap-x-3 rounded-md px-3 py-2 text-base/6 font-normal
                                {{ request()->routeIs('debug-monitor.dashboard')
                                    ? 'bg-gray-200 text-[#7746ec] dark:bg-[#1f2937] dark:text-[#a78bfa]'
                                    : 'text-gray-700 hover:bg-gray-200 hover:text-[#7746ec] dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-[#8b5cf6]' }}">
                                <svg class="h-[20px] w-[20px] shrink-0 text-[#7746ec] dark:text-[#8b5cf6] group-hover:text-[#7746ec] dark:group-hover:text-[#8b5cf6]"
                                    fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm9-9A2.25 2.25 0 0011 4.25v2.5A2.25 2.25 0 0013.25 9h2.5A2.25 2.25 0 0018 6.75v-2.5A2.25 2.25 0 0015.75 2h-2.5zm0 9A2.25 2.25 0 0011 13.25v2.5A2.25 2.25 0 0013.25 18h2.5A2.25 2.25 0 0018 15.75v-2.5A2.25 2.25 0 0015.75 11h-2.5z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('debug-monitor.rules.index') }}"
                                class="group flex items-center gap-x-3 rounded-md px-3 py-2 text-base/6 font-normal
                                    {{ request()->routeIs('debug-monitor.rules.*')
                                        ? 'bg-gray-200 text-[#7746ec] dark:bg-[#1f2937] dark:text-[#a78bfa]'
                                        : 'text-gray-700 hover:bg-gray-200 hover:text-[#7746ec] dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-[#8b5cf6]' }}">

                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                    data-slot="icon" aria-hidden="true" class="size-6 shrink-0 text-gray-400">
                                    <path
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Rules
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <div class="text-xs/6 font-semibold text-gray-400">Recent Errors</div>
                    <ul role="list" class="-mx-2 mt-2 space-y-1">
                        @forelse($recentErrors as $error)
                        <li>
                            <a href="{{ route('debug-monitor.rules.show', $error->debug_rule_id) }}"
                                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-200 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-[0.625rem] font-medium text-gray-400 group-hover:border-indigo-600 group-hover:text-indigo-600 dark:border-white/15 dark:bg-white/5 dark:group-hover:border-white/20 dark:group-hover:text-white">E</span>
                                <span class="truncate">{{ $error->rule->name }}</span>
                            </a>
                        </li>
                        @empty
                        <li> No errors found. </li>
                        @endforelse
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div>

<nav class="relative flex flex-1 flex-col mt-5">
    <ul role="list" class="flex flex-1 flex-col gap-y-7">
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
            <div class="text-xs/6 font-semibold text-gray-400">Recent Failed Rules</div>
            <ul role="list" class="-mx-2 mt-2 space-y-1">
                @forelse($recentFailedRules as $rule)
                    <li>
                        <a href="{{ route('debug-monitor.rules.show', $rule->id) }}"
                            class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-200 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-[0.625rem] font-medium text-gray-400 group-hover:border-indigo-600 group-hover:text-indigo-600 dark:border-white/15 dark:bg-white/5 dark:group-hover:border-white/20 dark:group-hover:text-white">E</span>
                            <span class="truncate">{{ $rule->name }}</span>
                        </a>
                    </li>
                @empty
                    <li> No errors found. </li>
                @endforelse
            </ul>
        </li>
    </ul>
</nav>

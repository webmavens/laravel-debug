<div class="hidden bg-gray-900 lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col h-[100vh]">
    <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 px-6">
        <div class="flex h-16 shrink-0 items-center">
            <img src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=600" alt="Your Company"
                class="h-8 w-auto" />
        </div>
        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                <li>
                    <ul role="list" class="-mx-2 space-y-1">
                        <li>
                            <a href="{{ route('debug-monitor.dashboard') }}"
                                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-white {{ request()->routeIs('debug-monitor.dashboard') ? 'bg-white/5 text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                    data-slot="icon" aria-hidden="true" class="size-6 shrink-0">
                                    <path
                                        d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('debug-monitor.rules.index') }}"
                                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold {{ request()->routeIs('debug-monitor.rules.*') ? 'bg-white/5 text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                    data-slot="icon" aria-hidden="true" class="size-6 shrink-0">
                                    <path d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                                Rules
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</div>

<div class="sticky top-0 z-40 flex items-center gap-x-6 bg-gray-900 px-4 py-4 shadow-sm sm:px-6 lg:hidden  h-[100vh]">
    <button type="button" command="show-modal" commandfor="sidebar"
        class="-m-2.5 p-2.5 text-gray-400 hover:text-white lg:hidden">
        <span class="sr-only">Open sidebar</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon"
            aria-hidden="true" class="size-6">
            <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
    <div class="flex-1 text-sm/6 font-semibold text-white">Dashboard</div>
</div>

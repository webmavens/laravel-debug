@props(['title'])

<div x-data="{ open: false }" class="relative">
    <button type="button" @mouseenter="open = true" @mouseleave="open = false" @focus="open = true" @blur="open = false"
        class="inline-flex items-center justify-center size-5 rounded-full bg-gray-200 text-gray-500 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-800">
        <span class="text-xs font-semibold">?</span>
    </button>
    <div x-cloak x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        @mouseenter="open = true" @mouseleave="open = false"
        class="absolute left-1/2 -translate-x-1/2 mt-2 z-50 w-72 rounded-lg bg-gray-900 px-3 py-2 text-sm text-white shadow-lg dark:bg-gray-700 dark:text-gray-100">
        <div class="font-medium mb-1">{{ $title }}</div>
        <div class="text-gray-300 dark:text-gray-400 text-xs leading-relaxed">{{ $slot }}</div>
        <div class="absolute -top-1 left-1/2 -translate-x-1/2 size-2 rotate-45 bg-gray-900 dark:bg-gray-700"></div>
    </div>
</div>

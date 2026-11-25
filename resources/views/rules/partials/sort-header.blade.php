{{-- A sortable column header link. Expects $key, $label, $sort, $direction and $sortLinks. --}}
<a href="{{ $sortLinks[$key] }}" class="group inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400"
    title="Sort by {{ strtolower($label) }}">
    {{ $label }}
    <span class="{{ $sort === $key ? 'text-gray-900 dark:text-white' : 'invisible text-gray-400 group-hover:visible group-focus-visible:visible' }}">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
            class="size-4 {{ $sort === $key && $direction === 'asc' ? 'rotate-180' : '' }}">
            <path fill-rule="evenodd" clip-rule="evenodd"
                d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" />
        </svg>
    </span>
</a>

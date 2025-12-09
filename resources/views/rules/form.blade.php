@extends('debug-monitor::layouts.app')

@section('content')
<div class="p-6 mt-[90px]">
    <div class="flex justify-between gap-2">
        <h1 class="text-xl font-bold mb-6">
            {{ isset($rule) ? 'Edit Rule' : 'Add New Rule' }}
        </h1>
        <a href="{{ route('debug-monitor.rules.index') }}"
            class="inline-flex items-center text-indigo-600 hover:text-indigo-700 text-lg mr-5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18">
                </path>
            </svg>
            <span class="ml-1 font-bold underline">Go Back</span>
        </a>
    </div>

    <form method="POST"
        action="{{ isset($rule) ? route('debug-monitor.rules.update', $rule->id) : route('debug-monitor.rules.store') }}"
        class="space-y-5">
        @csrf

        @if(isset($rule))
            @method('PUT')
        @endif

        <div class="relative">
            <label for="name" class="block text-sm/6 font-medium text-gray-900">Rule Name</label>
            <input
                type="text"
                name="name"
                value="{{ old('name', $rule->name ?? '') }}"
                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                id=""
                required
            >

            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="relative">
            <label for="sql_query" class="block text-sm/6 font-medium text-gray-900">
                SQL Query
            </label>
            <textarea
                name="sql_query"
                rows="4"
                id="sql_query"
                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                required
            >{{ old('sql_query', $rule->sql_query ?? '') }}</textarea>

            <p class="text-xs text-gray-500 mt-1">Example: <code>SELECT * FROM users WHERE firstname IS NULL</code></p>

            @error('sql_query')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="relative">
                <label for="expected_rows" class="block text-sm/6 font-medium text-gray-900">
                    Expected Rows
                </label>
                <div class="mt-2">
                    <div
                        class="flex rounded-md bg-white outline-1 -outline-offset-1 outline-gray-300 has-[input:focus-within]:outline-2 has-[input:focus-within]:-outline-offset-2 has-[input:focus-within]:outline-indigo-600">
                        <div class="grid shrink-0 grid-cols-1 focus-within:relative">
                            <select
                                name="expected_rows_operator"
                                id="expected_rows_operator"
                                class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-7 pl-3 text-base text-gray-500 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                            >
                                @php
                                    $operators = ['=', '>', '<', '>=' , '<=' ];
                                @endphp

                                @foreach ($operators as $op)
                                    <option
                                        value="{{ $op }}"
                                        {{ old('expected_rows_operator', $rule->expected_rows_operator ?? '=') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                @endforeach
                            </select>
                            <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true"
                                class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4">
                                <path
                                    d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                                    clip-rule="evenodd" fill-rule="evenodd" />
                            </svg>
                        </div>
                        <input
                            type="number"
                            name="expected_rows"
                            id="expected_rows"\
                            class="block min-w-0 grow bg-white py-1.5 pr-3 pl-1 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none sm:text-sm/6"
                            value="{{ old('expected_rows', $rule->expected_rows ?? 0) }}"
                        />

                        @error('expected_rows')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="relative">
                <label for="frequency_minutes" class="block text-sm/6 font-medium text-gray-900">
                    Frequency (minutes)
                </label>
                <input 
                    ype="number"
                    name="frequency_minutes"
                    id="frequency_minutes"
                    class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
                    value="{{ old('frequency_minutes', $rule->frequency_minutes ?? 15) }}"
                    required
                />

                @error('frequency_minutes')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="relative">
            <label for="expected_json" class="block text-sm/6 font-medium text-gray-900">
                Expected Data (JSON)
            </label>
            <textarea
                name="expected_json"
                rows="3"
                id="expected_json"
                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
            >{{ old('expected_json', $rule->expected_json ?? '') }}</textarea>

            @error('expected_json')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="relative">
                <label for="notification_level" class="block text-sm/6 font-medium text-gray-900">
                    Notification Type
                </label>
                <div class="mt-2 grid grid-cols-1">
                    <select
                        name="notification_level"
                        id="notification_level"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6"
                    >
                        @foreach(['default', 'urgent'] as $type)
                            <option value="{{ $type }}" @selected(old('notification_level', $rule->notification_level ?? '') === $type)>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4">
                        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>

                    @error('notification_level')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="relative">
                <label for="status" class="block text-sm/6 font-medium text-gray-900">
                    Status
                </label>
                <div class="mt-2 grid grid-cols-1">
                    <select
                        name="status"
                        id="status"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6"
                    >
                        @foreach(['active', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $rule->status ?? 'active') === $status)>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4">
                        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>

                    @error('status')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="relative">
                <label for="importance_level" class="block text-sm/6 font-medium text-gray-900">
                    Importance
                </label>
                <div class="mt-2 grid grid-cols-1">
                    <select
                        name="importance_level"
                        id="importance_level"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6"
                    >
                        @foreach(['high', 'medium', 'low'] as $level)
                        <option value="{{ $level }}" @selected(old('importance_level', $rule->importance_level ?? '') ===
                            $level)>{{ ucfirst($level) }}</option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4">
                        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>

                    @error('importance_level')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="py-4">
            <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 cursor-pointer" id="submit_btn">
                {{ isset($rule) ? 'Update Rule' : 'Save Rule' }}
            </button>
            <a href="{{ route('debug-monitor.rules.index') }}"
                class="ml-4 bg-red-600 hover:bg-red-700 text-white inline-block py-2 px-5 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelector("form").addEventListener("submit", function() {
    const btn = document.getElementById("submit_btn");
    btn.disabled = true;
    btn.innerText = "Processing...";
    btn.classList.add("opacity-50", "cursor-not-allowed");
});
</script>
@endpush

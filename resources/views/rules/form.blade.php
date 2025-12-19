@extends('debug-monitor::layouts.app')

@section('content')

<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ isset($rule) ? 'Edit Rule' : 'Add New Rule' }}
        </h1>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <div class="flex items-center gap-2">
            <a href="{{ route('debug-monitor.rules.index') }}"
                class="text-sm flex items-center gap-1 text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 18h3.75a5.25 5.25 0 100-10.5H5M7.5 4L4 7.5 7.5 11">
                    </path>
                </svg>
                Go Back
            </a>
        </div>
    </div>
</div>

<div class="mt-8">
    <form method="POST"
        action="{{ isset($rule) ? route('debug-monitor.rules.update', $rule->id) : route('debug-monitor.rules.store') }}"
        class="bg-white shadow-sm outline outline-1 outline-gray-900/5 sm:rounded-xl md:col-span-2 dark:bg-gray-800/50 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
        @csrf
        @if(isset($rule))
            @method('PUT')
        @endif

        <div class="px-4 py-6 sm:p-8">
            <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <label for="name" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        Rule Name
                    </label>
                    <div class="mt-2">
                        <input id="name" type="text" name="name" value="{{ old('name', $rule->name ?? '') }}"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="col-span-full">
                    <label for="sql_query"
                        class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        SQL Query
                    </label>
                    <div class="mt-2">
                        <textarea id="sql_query" name="sql_query" rows="3"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white
                                dark:outline dark:outline-1 dark:-outline-offset-1 dark:outline-gray-600
                                dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">{{ old('sql_query', $rule->sql_query ?? '') }}</textarea>
                    </div>
                    <p class="mt-2 text-xs leading-6 text-gray-600 dark:text-gray-400">
                        Example: SELECT * FROM users WHERE firstname IS NULL
                    </p>

                    @error('sql_query')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-3">
                    <label for="expected_rows"
                        class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        Expected Rows
                    </label>

                    <div class="mt-2">
                        <div class="flex items-center rounded-md bg-white pl-3
                        outline outline-1 -outline-offset-1 outline-gray-300
                        focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600
                        dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-indigo-500">
                            <div class="grid shrink-0 grid-cols-1 focus-within:relative">
                                <select name="expected_rows_operator" id="expected_rows_operator"
                                    class="col-start-1 row-start-1 appearance-none rounded-md bg-white dark:bg-gray-800 py-1 pr-7 pl-3 text-base text-gray-700 dark:text-gray-200 placeholder:text-gray-400 focus:outline-0 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
                                    @php
                                        $operators = ['=', '>', '<', '>=' , '<=' ];
                                    @endphp

                                    @foreach ($operators as $op)
                                        <option value="{{ $op }}" @selected(old('expected_rows_operator',
                                        $rule->
                                        expected_rows_operator ?? '=') == $op)>
                                        {{ $op }}
                                        </option>
                                    @endforeach
                                </select>
                                <svg viewBox="0 0 16 16" fill="currentColor"
                                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 dark:text-gray-400 sm:size-4">
                                    <path
                                        d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" />
                                </svg>
                            </div>
                            <input type="number" name="expected_rows" id="expected_rows" class="block min-w-0 grow bg-white py-1.5 pl-1 pr-3
                                    text-base text-gray-900 placeholder:text-gray-400
                                    focus:outline-none
                                    sm:text-sm sm:leading-6
                                    dark:bg-transparent dark:text-white dark:placeholder:text-gray-500"
                                value="{{ old('expected_rows', $rule->expected_rows ?? 0) }}" />
                            @error('expected_rows')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="frequency_minutes"
                        class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        Frequency (minutes)
                    </label>
                    <div class="mt-2">
                        <input name="frequency_minutes" id="frequency_minutes" type="number" value="15"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                            value="{{ old('frequency_minutes', $rule->frequency_minutes ?? 15) }}" />
                        @error('frequency_minutes')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="col-span-full">
                    <label for="expected_json"
                        class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        Expected Data (JSON)
                    </label>
                    <div class="mt-2">
                        <textarea id="expected_json" name="expected_json" rows="3"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white
                                dark:outline dark:outline-1 dark:-outline-offset-1 dark:outline-gray-600
                                dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">{{ old('expected_json', $rule->expected_json ?? '') }}</textarea>
                        @error('expected_json')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @foreach([
                'notification_level' => ['default', 'urgent'],
                'status' => ['active', 'inactive'],
                'importance_level' => ['high', 'medium', 'low']
                ] as $field => $options)
                    <div class="sm:col-span-3">

                        <label for="notification_level"
                            class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                            {{ ucwords(str_replace('_',' ', $field)) }}
                        </label>
                        <div class="mt-2 relative">
                            <select name="{{ $field }}" id="{{ $field }}"
                                class="block w-full appearance-none rounded-md bg-white py-1.5 pl-3 pr-8
                                text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-gray-800 dark:text-gray-200 dark:outline-white/10 dark:focus:outline-indigo-500">
                                @foreach($options as $op)
                                    <option value="{{ $op }}" @selected(old($field, $rule->$field ?? '') === $op)>
                                        {{ ucfirst($op) }}
                                    </option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 size-4 text-gray-500 dark:text-gray-400"
                                viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" />
                            </svg>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Footer -->
        <div
            class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 dark:border-white/10">
            <a href="{{ route('debug-monitor.rules.index') }}"
                class="text-sm leading-6 font-semibold text-gray-900 dark:text-white">Cancel</a>
            <button type="submit"
                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm
                            hover:bg-indigo-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600
                            dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus:outline-indigo-500" id="submit-btn">
                {{ isset($rule) ? 'Update Rule' : 'Save Rule' }}
            </button>
        </div>
    </form>

</div>
@endsection

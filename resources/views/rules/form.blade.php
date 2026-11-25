@extends('debug-monitor::layouts.app')

@section('content')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
    // "Test query": post the form's query and expectations, show the outcome.
    document.addEventListener('alpine:init', () => {
        Alpine.data('queryTester', (url) => ({
            loading: false,
            result: null,
            error: null,

            async run(form) {
                this.loading = true;
                this.result = null;
                this.error = null;

                const data = new FormData(form);
                data.delete('_method'); // the edit form is sent as PUT; this endpoint takes POST

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        body: data,
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const body = await response.json().catch(() => null);

                    if (response.status === 422 && body && body.errors) {
                        this.error = Object.values(body.errors)[0][0];
                    } else if (response.status === 419) {
                        this.error = 'Your session has expired. Reload the page and try again.';
                    } else if (! response.ok || ! body) {
                        this.error = 'The test could not run (HTTP ' + response.status + ').';
                    } else {
                        this.result = body;
                    }
                } catch (e) {
                    this.error = 'The test could not run: ' + e.message;
                } finally {
                    this.loading = false;
                }
            },

            get columns() {
                return this.result && this.result.sample.length ? Object.keys(this.result.sample[0]) : [];
            },

            cell(value) {
                if (value === null || value === undefined) return 'NULL';

                return typeof value === 'object' ? JSON.stringify(value) : String(value);
            },

            formatDuration(ms) {
                return ms < 1000 ? ms + ' ms' : (ms / 1000).toFixed(2).replace(/\.?0+$/, '') + ' s';
            },
        }));
    });
</script>
@endpush

<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ isset($rule) ? 'Edit Rule' : 'Add New Rule' }}
        </h1>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <div class="flex items-center gap-2">
            <a href="{{ $indexUrl ?? route('debug-monitor.rules.index') }}"
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

                {{-- Name takes the remaining width; the numeric fields stay compact. --}}
                <div class="col-span-full flex flex-col gap-x-6 gap-y-8 sm:flex-row">
                    <div class="sm:min-w-0 sm:flex-1">
                        <label for="name" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                            Rule Name
                        </label>
                        <div class="mt-2">
                            <input id="name" type="text" name="name" placeholder="e.g. Users without an email" value="{{ old('name', $rule->name ?? '') }}"
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

                    <div class="sm:w-48 sm:shrink-0">
                        <div class="flex items-center gap-2">
                            <label for="group_name" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                                Group
                            </label>
                            <x-debug-monitor::tooltip title="Group">
                                Optional. The area this rule belongs to, e.g. <strong class="text-white dark:text-gray-200">Invoices</strong>. The rules list can be filtered by group, so you can see, run or suppress an area's rules together.
                            </x-debug-monitor::tooltip>
                        </div>
                        <div class="mt-2">
                            <input id="group_name" type="text" name="group_name" maxlength="100" list="debug-monitor-groups"
                                placeholder="e.g. Invoices" value="{{ old('group_name', $rule->group_name ?? '') }}"
                                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                    outline outline-1 -outline-offset-1 outline-gray-300
                                    placeholder:text-gray-400
                                    focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                    sm:text-sm sm:leading-6
                                    dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
                            <datalist id="debug-monitor-groups">
                                @foreach($groups ?? [] as $groupOption)
                                    <option value="{{ $groupOption }}">
                                @endforeach
                            </datalist>
                            @error('group_name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="sm:w-40 sm:shrink-0">
                        <div class="flex items-center gap-2">
                            <label for="expected_rows" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                                Expected Rows
                            </label>
                            <x-debug-monitor::tooltip title="Expected Rows">
                                Set the expected number of rows the SQL query should return. Choose an operator (=, &gt;, &lt;, &gt;=, &lt;=) and a count. The rule fails if the actual count doesn't match.
                            </x-debug-monitor::tooltip>
                        </div>
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
                                            <option value="{{ $op }}" @selected(old('expected_rows_operator', $rule->expected_rows_operator ?? '=') == $op)>
                                                {{ $op }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <svg viewBox="0 0 16 16" fill="currentColor"
                                        class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 dark:text-gray-400 sm:size-4">
                                        <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" />
                                    </svg>
                                </div>
                                <input type="number" name="expected_rows" id="expected_rows" min="0" placeholder="e.g. 0"
                                    class="block min-w-0 grow bg-white py-1.5 pl-1 pr-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none sm:text-sm sm:leading-6 dark:bg-transparent dark:text-white dark:placeholder:text-gray-500"
                                    value="{{ old('expected_rows', $rule->expected_rows ?? 0) }}" />
                            </div>
                            @error('expected_rows')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="sm:w-48 sm:shrink-0">
                        <div class="flex items-center gap-2">
                            <label for="frequency_minutes" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                                Frequency (minutes)
                            </label>
                            <x-debug-monitor::tooltip title="Frequency">
                                How often (in minutes) this rule should be checked. For example, <strong class="text-white dark:text-gray-200">5</strong> means it runs every 5 minutes. The scheduler runs every minute and picks up rules that are due.
                            </x-debug-monitor::tooltip>
                        </div>
                        <div class="mt-2">
                            <input name="frequency_minutes" id="frequency_minutes" type="number" min="1" placeholder="e.g. 15"
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
                </div>

                <div class="col-span-full">
                    <div class="flex items-center gap-2">
                        <label for="sql_query" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                            SQL Query
                        </label>
                        <x-debug-monitor::tooltip title="SQL Query">
                            Enter a SELECT query to monitor. The row count and data are compared against your expected values on each run.
                        </x-debug-monitor::tooltip>
                    </div>
                    <div class="mt-2">
                        <textarea id="sql_query" name="sql_query" rows="3" placeholder="e.g. SELECT id, name FROM users WHERE email IS NULL"
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

                    {{-- Run the query once against the expectations on this form, without saving. --}}
                    <div x-data="queryTester($el.dataset.url)" data-url="{{ route('debug-monitor.rules.test') }}" class="mt-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="run($el.closest('form'))" :disabled="loading"
                                class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:cursor-wait disabled:opacity-60 dark:bg-white/10 dark:text-white dark:ring-white/10 dark:hover:bg-white/20">
                                <svg class="size-4 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.841Z" />
                                </svg>
                                <span x-text="loading ? 'Testing…' : 'Test query'">Test query</span>
                            </button>
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                Runs the query once against the expected rows and data on this form. Nothing is saved and no alert is sent.
                            </span>
                        </div>

                        <div x-show="error" x-cloak role="alert"
                            class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300" x-text="error"></div>

                        <template x-if="result">
                            <div role="status" class="mt-3 rounded-md p-3 text-sm outline outline-1 -outline-offset-1"
                                :class="result.status === 'ok' ? 'bg-green-50 outline-green-600/20 dark:bg-green-500/10 dark:outline-green-500/20' : 'bg-red-50 outline-red-600/20 dark:bg-red-500/10 dark:outline-red-500/20'">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="result.status === 'ok' ? 'bg-green-100 text-green-700 dark:bg-green-400/10 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-400'"
                                        x-text="{ ok: 'Would pass', failed: 'Would fail', error: 'Query error' }[result.status]"></span>
                                    <span class="text-gray-700 dark:text-gray-300" x-show="result.rows !== null"
                                        x-text="result.rows + (result.rows === 1 ? ' row' : ' rows') + ' · ' + formatDuration(result.duration_ms)"></span>
                                </div>

                                <ul class="mt-2 list-disc pl-5 text-red-700 dark:text-red-300" x-show="result.problems.length">
                                    <template x-for="problem in result.problems">
                                        <li x-text="problem"></li>
                                    </template>
                                </ul>
                                <p class="mt-2 break-words font-mono text-xs text-red-700 dark:text-red-300" x-show="result.error" x-text="result.error"></p>

                                <div class="mt-3 max-h-80 overflow-auto rounded-md bg-white outline outline-1 -outline-offset-1 outline-gray-900/10 dark:bg-gray-900 dark:outline-white/10" x-show="result.sample.length">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10">
                                        <thead class="bg-gray-50 dark:bg-gray-800/75">
                                            <tr>
                                                <template x-for="column in columns">
                                                    <th scope="col" class="whitespace-nowrap px-3 py-2 text-left font-semibold text-gray-900 dark:text-gray-200" x-text="column"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                            <template x-for="row in result.sample">
                                                <tr>
                                                    <template x-for="column in columns">
                                                        <td class="whitespace-nowrap px-3 py-1.5 font-mono text-gray-700 dark:text-gray-300" x-text="cell(row[column])"></td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400" x-show="result.sample_truncated"
                                    x-text="'Showing the first ' + result.sample.length + ' of ' + result.rows + ' rows.'"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="col-span-full">
                    <div class="flex items-center gap-2">
                        <label for="expected_json" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                            Expected Data (JSON)
                        </label>
                        <x-debug-monitor::tooltip title="Expected Data (JSON)">
                            Optional. Provide a JSON object where keys are column names. At least one result row must match <strong class="text-white dark:text-gray-200">all</strong> specified values. Leave empty to skip data validation.
                            <div class="mt-2 rounded bg-gray-800 px-2 py-1.5 dark:bg-gray-900">
                                <code class="text-xs text-green-400">{ "status": "active", "role": "admin" }</code>
                            </div>
                        </x-debug-monitor::tooltip>
                    </div>
                    <div class="mt-2">
                        <textarea id="expected_json" name="expected_json" rows="3"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white
                                dark:outline dark:outline-1 dark:-outline-offset-1 dark:outline-gray-600
                                dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
                            placeholder='e.g. {"status": "active", "role": "admin"}'>{{ is_array(old('expected_json', $rule->expected_json ?? '')) ? json_encode(old('expected_json', $rule->expected_json ?? ''), JSON_PRETTY_PRINT) : old('expected_json', $rule->expected_json ?? '') }}</textarea>
                        @error('expected_json')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="mt-2 text-xs leading-6 text-gray-600 dark:text-gray-400">
                        JSON object where keys are column names. At least one row must match all specified values.
                        Leave empty to skip data validation.
                    </p>
                </div>

                @foreach([
                    'notification_level' => ['none', 'default', 'urgent'],
                    'status' => ['active', 'inactive'],
                    'importance_level' => ['high', 'medium', 'low']
                ] as $field => $options)
                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-2">
                            <label for="{{ $field }}" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                                {{ ucwords(str_replace('_', ' ', $field)) }}
                            </label>
                            <x-debug-monitor::tooltip :title="ucwords(str_replace('_', ' ', $field))">
                                @if($field === 'notification_level')
                                    Controls email alerts when the rule fails.
                                    <ul class="mt-1.5 space-y-0.5">
                                        <li><strong class="text-white dark:text-gray-200">None</strong> &mdash; Log only, no email sent.</li>
                                        <li><strong class="text-white dark:text-gray-200">Default</strong> &mdash; Send a standard alert email.</li>
                                        <li><strong class="text-white dark:text-gray-200">Urgent</strong> &mdash; Same alert, sent as high priority with &ldquo;[Urgent]&rdquo; in the subject.</li>
                                    </ul>
                                @elseif($field === 'status')
                                    Toggle whether the rule is actively being checked.
                                    <ul class="mt-1.5 space-y-0.5">
                                        <li><strong class="text-white dark:text-gray-200">Active</strong> &mdash; Rule runs on schedule.</li>
                                        <li><strong class="text-white dark:text-gray-200">Inactive</strong> &mdash; Rule is paused and will not run.</li>
                                    </ul>
                                @elseif($field === 'importance_level')
                                    Set the priority of this rule for filtering and display.
                                    <ul class="mt-1.5 space-y-0.5">
                                        <li><strong class="text-white dark:text-gray-200">Low</strong> &mdash; Informational, non-critical.</li>
                                        <li><strong class="text-white dark:text-gray-200">Medium</strong> &mdash; Worth monitoring.</li>
                                        <li><strong class="text-white dark:text-gray-200">High</strong> &mdash; Critical, investigate immediately.</li>
                                    </ul>
                                @endif
                            </x-debug-monitor::tooltip>
                        </div>
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
        <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 dark:border-white/10">
            <a href="{{ $indexUrl ?? route('debug-monitor.rules.index') }}"
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

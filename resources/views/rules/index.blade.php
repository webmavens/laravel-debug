@extends('debug-monitor::layouts.app')

@section('content')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">All Rules</h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">A list of all the rules</p>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center gap-4">
        <form method="POST" action="{{ route('debug-monitor.rules.sync') }}" class="inline">
            @csrf
            <button type="submit"
                onclick="return confirm('Import new rule files from the rules directory into the database?')"
                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Sync from files
                @if(($pendingCount ?? 0) > 0)
                    <span class="ml-1 inline-flex items-center rounded-full bg-white/25 px-1.5 py-0.5 text-xs font-semibold">
                        {{ $pendingCount }} pending
                    </span>
                @endif
            </button>
        </form>
        <form method="GET" id="filter" class="flex flex-wrap items-center gap-2">
            @if($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            @endif
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, group or SQL…" aria-label="Search rules"
                onsearch="document.getElementById('filter').submit()"
                class="rounded-md bg-white py-1.5 pl-3 pr-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500" />

            <div class="grid grid-cols-1">
                <select name="status" id="status"
                    class="col-start-1 row-start-1 appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:*:bg-gray-800 dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                    onchange="document.getElementById('filter').submit()">
                    @foreach(['all' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'suppressed' => 'Suppressed', 'failing' => 'Failing'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                    <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
            </div>

            @if(! empty($groups))
            <div class="grid grid-cols-1">
                <select name="group" id="group" aria-label="Group"
                    class="col-start-1 row-start-1 appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:*:bg-gray-800 dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                    onchange="document.getElementById('filter').submit()">
                    <option value="" @selected(! request('group'))>All groups</option>
                    @foreach($groups as $groupOption)
                        <option value="{{ $groupOption }}" @selected(request('group') === $groupOption)>{{ $groupOption }}</option>
                    @endforeach
                    <option value="{{ $noGroup }}" @selected(request('group') === $noGroup)>No group</option>
                </select>
                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                    <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
            </div>
            @endif

            <div class="grid grid-cols-1">
                <select name="importance_level" id="importance_level"
                    class="col-start-1 row-start-1 appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:*:bg-gray-800 dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                    onchange="document.getElementById('filter').submit()">
                    @foreach(['all' => 'All importance', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('importance_level', 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                    <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
            </div>

            <div class="grid grid-cols-1">
                <select name="per_page" id="per_page" aria-label="Rows per page"
                    class="col-start-1 row-start-1 appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:*:bg-gray-800 dark:outline-white/10 dark:focus-visible:outline-indigo-500"
                    onchange="document.getElementById('filter').submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per page</option>
                    @endforeach
                </select>
                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                    class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                    <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
            </div>
        </form>
    </div>
</div>

<div class="mt-8 flow-root"
    x-data="{
        ids: @js($rules->pluck('id')->map(fn ($id) => (string) $id)->values()),
        selected: [],
        get allSelected() { return this.ids.length > 0 && this.selected.length === this.ids.length; },
        toggleAll(checked) { this.selected = checked ? [...this.ids] : []; },
    }">

    {{-- Bulk actions for the rules selected on this page --}}
    <div x-show="selected.length > 0" x-cloak
        class="mb-3 flex flex-wrap items-center gap-3 rounded-md bg-indigo-50 px-4 py-2.5 text-sm dark:bg-indigo-500/10 dark:outline dark:outline-indigo-500/20">
        <span class="font-semibold text-indigo-900 dark:text-indigo-200" x-text="selected.length + ' selected'"></span>

        <form id="bulk-form" method="POST" action="{{ route('debug-monitor.rules.bulk') }}"
            class="flex flex-wrap items-center gap-2">
            @csrf
            @php
                $bulkBtn = 'rounded-md bg-white px-2.5 py-1 text-xs font-semibold shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-white/10 dark:ring-white/10 dark:hover:bg-white/20';
                $divider = 'mx-1 h-4 w-px bg-indigo-200 dark:bg-indigo-500/30';
            @endphp
            <button type="submit" name="action" value="run"
                class="{{ $bulkBtn }} text-amber-700 dark:text-amber-400">
                Run
            </button>
            <button type="submit" name="action" value="export"
                @click="if (! confirm('Export ' + selected.length + ' rule(s) to version-controlled JSON files? Rules already linked to a file are skipped.')) $event.preventDefault()"
                class="{{ $bulkBtn }} text-indigo-700 dark:text-indigo-300">
                Export
            </button>

            <span class="{{ $divider }}" aria-hidden="true"></span>

            <button type="submit" name="action" value="activate"
                class="{{ $bulkBtn }} text-green-700 dark:text-green-400">
                Activate
            </button>
            <button type="submit" name="action" value="deactivate"
                @click="if (! confirm('Deactivate ' + selected.length + ' rule(s)? They will stop running on schedule.')) $event.preventDefault()"
                class="{{ $bulkBtn }} text-gray-700 dark:text-gray-300">
                Deactivate
            </button>

            <span class="{{ $divider }}" aria-hidden="true"></span>

            <button type="submit" name="action" value="suppress"
                @click="if (! confirm('Suppress ' + selected.length + ' rule(s)? They keep running and logging, but no alerts will be sent.')) $event.preventDefault()"
                class="{{ $bulkBtn }} text-indigo-700 dark:text-indigo-300">
                Suppress
            </button>
            <select name="duration" aria-label="Suppress for"
                class="rounded-md bg-white py-1 pl-2 pr-7 text-xs font-medium text-gray-700 shadow-xs ring-1 ring-gray-300 ring-inset dark:bg-white/10 dark:text-gray-200 dark:ring-white/10 dark:*:bg-gray-800">
                <option value="forever">until unsuppressed</option>
                <option value="1">for 1 hour</option>
                <option value="24">for 24 hours</option>
                <option value="168">for 7 days</option>
            </select>
            <button type="submit" name="action" value="unsuppress"
                class="{{ $bulkBtn }} text-indigo-700 dark:text-indigo-300">
                Unsuppress
            </button>

            <span class="{{ $divider }}" aria-hidden="true"></span>

            {{-- Enter here sets the group (instead of submitting with the first button, Run). --}}
            <input type="text" name="group_name" list="debug-monitor-groups" maxlength="100"
                placeholder="Group name" aria-label="Group name for the selected rules"
                @keydown.enter.prevent="$refs.setGroup.click()"
                class="w-32 rounded-md bg-white px-2 py-1 text-xs text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset placeholder:text-gray-400 dark:bg-white/10 dark:text-white dark:ring-white/10" />
            <datalist id="debug-monitor-groups">
                @foreach($groups as $groupOption)
                    <option value="{{ $groupOption }}">
                @endforeach
            </datalist>
            <button type="submit" name="action" value="group" x-ref="setGroup"
                @click="const group = $el.form.elements.group_name.value.trim(); if (! confirm(group ? 'Move ' + selected.length + ' rule(s) to group &quot;' + group + '&quot;?' : 'Remove ' + selected.length + ' rule(s) from their group?')) $event.preventDefault()"
                class="{{ $bulkBtn }} text-gray-700 dark:text-gray-300">
                Set group
            </button>

            <span class="{{ $divider }}" aria-hidden="true"></span>

            <button type="submit" name="action" value="delete"
                @click="if (! confirm('Delete ' + selected.length + ' rule(s)? Linked rule files will be deleted too.')) $event.preventDefault()"
                class="rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white shadow-xs hover:bg-red-500 dark:bg-red-500 dark:hover:bg-red-400">
                Delete
            </button>
        </form>

        <button type="button" @click="selected = []"
            class="ml-auto text-xs font-medium text-indigo-700 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-indigo-200">
            Clear selection
        </button>
    </div>

    <div class="-my-2 overflow-hidden">
        <div class="inline-block w-full py-2 align-middle">
            <div
                class="overflow-hidden shadow-sm outline-1 outline-black/5 sm:rounded-lg dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">

                <table class="relative w-full table-auto divide-y divide-gray-300 dark:divide-white/15">
                    <thead class="bg-white dark:bg-gray-800/75">
                        <tr>
                            <th scope="col" class="w-10 py-3.5 pl-4 sm:pl-6">
                                <input type="checkbox" aria-label="Select all rules on this page"
                                    :checked="allSelected"
                                    x-effect="$el.indeterminate = selected.length > 0 && ! allSelected"
                                    @change="toggleAll($event.target.checked)"
                                    class="size-4 cursor-pointer accent-indigo-600" />
                            </th>
                            <th scope="col" @if($sort === 'name') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
                                class="w-2/5 sm:w-1/5 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                @include('debug-monitor::rules.partials.sort-header', ['key' => 'name', 'label' => 'Name'])
                            </th>
                            <th scope="col"
                                class="w-2/5 sm:w-1/3 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                Query
                            </th>
                            <th scope="col" @if($sort === 'status') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
                                class="w-1/12 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                @include('debug-monitor::rules.partials.sort-header', ['key' => 'status', 'label' => 'Status'])
                            </th>
                            <th scope="col" @if($sort === 'last_run') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
                                class="w-1/12 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                @include('debug-monitor::rules.partials.sort-header', ['key' => 'last_run', 'label' => 'Last Run'])
                            </th>
                            <th scope="col"
                                class="w-24 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                <span class="inline-flex items-center gap-1">
                                    Suppress

                                    <span class="group relative inline-flex">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor"
                                            class="size-5 cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                        </svg>

                                        <span class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 w-72 -translate-x-1/2
                                        rounded-md bg-gray-900 px-3 py-2 text-xs font-normal text-white shadow-lg
                                        opacity-0 scale-95 transition
                                        group-hover:opacity-100 group-hover:scale-100
                                        dark:bg-gray-800">
                                            Suppress means this rule will still run and log results, but no email or
                                            alert will be
                                            sent.
                                        </span>
                                    </span>
                                </span>
                            </th>

                            <th scope="col"
                                class="w-40 py-3.5 pr-4 pl-3 text-sm font-semibold text-gray-900 dark:text-gray-200 text-right sm:pr-6">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-800/50">
                        @forelse($rules as $rule)
                            <tr :class="selected.includes('{{ $rule->id }}') && 'bg-indigo-50/60 dark:bg-indigo-500/5'">
                                <td class="py-4 pl-4 sm:pl-6">
                                    <input type="checkbox" name="ids[]" value="{{ $rule->id }}" form="bulk-form"
                                        x-model="selected" aria-label="Select rule {{ $rule->name }}"
                                        class="size-4 cursor-pointer accent-indigo-600" />
                                </td>
                                <td
                                    class="px-3 py-4 text-sm font-medium whitespace-normal break-words truncate dark:text-white">
                                    {{ $rule->name }}
                                    @if(in_array($rule->id, $fileRuleIds ?? []))
                                        <span title="Imported from a rule file"
                                            class="ml-1 inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-400">
                                            file
                                        </span>
                                    @endif
                                    @if($rule->group_name)
                                        <a href="{{ route('debug-monitor.rules.index', ['group' => $rule->group_name]) }}"
                                            title="Show the rules in this group"
                                            class="ml-1 inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-400/10 dark:text-indigo-300 dark:hover:bg-indigo-400/20">
                                            {{ $rule->group_name }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-sm whitespace-normal break-words max-w-[220px] sm:max-w-[520px] text-gray-500 dark:text-gray-400">
                                    {{ Str::limit($rule->sql_query, 70) }}</td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    <form action="{{ route('debug-monitor.rules.status', $rule->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        <button type="submit"
                                            title="Click to {{ $rule->status == 'active' ? 'deactivate' : 'activate' }} this rule"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-xs font-medium {{ $rule->status == 'active' ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-400/10 dark:text-green-400 dark:hover:bg-green-400/20' : 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-400/10 dark:text-red-400 dark:hover:bg-red-400/20' }}">
                                            {{ ucfirst($rule->status) }}
                                            <svg class="size-3 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M13.2 2.24a.75.75 0 0 0 .04 1.06l2.1 1.95H6.75a.75.75 0 0 0 0 1.5h8.59l-2.1 1.95a.75.75 0 1 0 1.02 1.1l3.5-3.25a.75.75 0 0 0 0-1.1l-3.5-3.25a.75.75 0 0 0-1.06.04Zm-6.4 8a.75.75 0 0 0-1.06-.04l-3.5 3.25a.75.75 0 0 0 0 1.1l3.5 3.25a.75.75 0 1 0 1.02-1.1l-2.1-1.95h8.59a.75.75 0 0 0 0-1.5H4.66l2.1-1.95a.75.75 0 0 0 .04-1.06Z" />
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    @if($rule->last_status)
                                        <span title="Result of the latest run"
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $rule->last_status === 'ok' ? 'bg-green-100 text-green-700 dark:bg-green-400/10 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-400' }}">
                                            {{ $rule->last_status === 'ok' ? 'Passed' : 'Failed' }}
                                        </span>
                                    @endif
                                    <div class="{{ $rule->last_status ? 'mt-1 text-xs' : '' }}"
                                        @if($rule->last_duration_ms !== null) title="Took {{ \Webmavens\DebugMonitor\Support\Duration::format($rule->last_duration_ms) }}" @endif>
                                        {{ $rule->last_run_at ? $rule->last_run_at->diffForHumans() : 'Never' }}
                                    </div>
                                    {{-- Only once it failed more than one run in a row; a first failure is already "Failed · just now". --}}
                                    @if($rule->last_status === 'failed' && $rule->failing_since && $rule->last_run_at && $rule->failing_since->lt($rule->last_run_at))
                                        <div class="mt-0.5 text-xs text-red-600 dark:text-red-400"
                                            title="Failing since {{ $rule->failing_since->toDayDateTimeString() }}">
                                            Failing for {{ $rule->failing_since->diffForHumans(null, true) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        @if($rule->isSuppressed())
                                            <input type="hidden" name="action" value="unsuppress">
                                            <button type="submit"
                                                class="rounded-sm bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
                                                onclick="return confirm('Are you sure you want to unsuppress this rule?')">Unsuppress</button>
                                            @if($rule->suppressed_until)
                                                <div class="mt-1 text-xs" title="Suppressed until {{ $rule->suppressed_until->toDayDateTimeString() }}">
                                                    for {{ $rule->suppressed_until->diffForHumans(null, true) }}
                                                </div>
                                            @endif
                                        @else
                                            {{-- Picking a duration submits the form. --}}
                                            <input type="hidden" name="action" value="suppress">
                                            <div class="grid grid-cols-1">
                                                <select name="duration" aria-label="Suppress {{ $rule->name }}"
                                                    onchange="if (this.value) this.form.requestSubmit()"
                                                    class="col-start-1 row-start-1 cursor-pointer appearance-none rounded-sm bg-indigo-600 py-1 pr-6 pl-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 *:bg-white *:font-normal *:text-gray-900 dark:*:bg-gray-800 dark:*:text-gray-200">
                                                    <option value="" selected disabled hidden>Suppress</option>
                                                    <option value="1">For 1 hour</option>
                                                    <option value="24">For 24 hours</option>
                                                    <option value="168">For 7 days</option>
                                                    <option value="forever">Until unsuppressed</option>
                                                </select>
                                                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                                    class="pointer-events-none col-start-1 row-start-1 mr-1.5 size-3.5 self-center justify-self-end text-white/80">
                                                    <path d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                </svg>
                                            </div>
                                        @endif
                                    </form>
                                </td>
                                <td class="py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6">
                                    @php $isFileRule = in_array($rule->id, $fileRuleIds ?? []); @endphp
                                    <span class="flex flex-wrap justify-end gap-2">
                                        <form action="{{ route('debug-monitor.rules.run', $rule->id) }}" method="post"
                                            class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300">
                                                Run now
                                            </button>
                                        </form>
                                        <a href="{{ route('debug-monitor.rules.show', $rule->id) }}"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Show</a>
                                        <a href="{{ route('debug-monitor.rules.edit', $rule->id) }}"
                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">Edit</a>
                                        @unless($isFileRule)
                                            <form action="{{ route('debug-monitor.rules.export', $rule->id) }}" method="post"
                                                class="inline">
                                                @csrf
                                                <button type="submit" onclick="return confirm('Export this rule to a version-controlled JSON file?')"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Export
                                                </button>
                                            </form>
                                        @endunless
                                        <form action="{{ route('debug-monitor.rules.destroy', $rule) }}" method="post"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                onclick="return confirm('{{ $isFileRule ? 'Delete this rule AND its linked file?' : 'Are you sure?' }}')"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                Delete
                                            </button>
                                        </form>
                                    </span>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">
                                    No records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3 dark:text-gray-300">
                    {{ $rules->links('pagination::tailwind') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

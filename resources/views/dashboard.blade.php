@extends('debug-monitor::layouts.app')

@section('content')

    <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Overview</h3>
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-4">
            <div
                class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
                <dt class="truncate text-sm font-semibold text-gray-500 dark:text-gray-400">Total Rules</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $totalRules }}</dd>
            </div>
            <div
                class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
                <dt class="truncate text-sm font-semibold text-gray-500 dark:text-gray-400">Active Rules</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $activeRules }}</dd>
            </div>
            <div
                class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
                <dt class="truncate text-sm font-semibold text-gray-500 dark:text-gray-400">Inactive Rules</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $inactiveRules }}</dd>
            </div>
            <div
                class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm sm:p-6 dark:bg-gray-800/75 dark:inset-ring dark:inset-ring-white/10">
                <dt class="truncate text-sm font-semibold text-gray-500 dark:text-gray-400">Suppressed Rules</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $suppressRules }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-9">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Recent Errors (Last 24 Hours)</h3>
            </div>
        </div>
        <div class="mt-5 flow-root ">
            <div class="">
                <div class="w-full py-2 align-middle">
                    <div
                        class="overflow-hidden shadow-sm outline-1 outline-black/5 sm:rounded-lg dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                        <table class="relative w-full table-auto divide-y divide-gray-300 dark:divide-white/15">
                            <thead class="bg-white dark:bg-gray-800/75">
                                <tr>
                                    <th scope="col"
                                        class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 dark:text-gray-200">
                                        Rule</th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                        Latest Result
                                    </th>
                                    <th scope="col"
                                        class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                        Last Failed
                                    </th>
                                    <th scope="col"
                                        class="py-3.5 pr-4 pl-3 sm:pr-6 text-right text-sm font-semibold text-gray-900 dark:text-gray-200">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-800/50">
                                @forelse($recentErrors as $error)
                                    <tr>
                                        <td
                                            class="py-4 pr-3 pl-4 text-sm font-medium break-words text-gray-900 sm:pl-6 dark:text-white">
                                            {{ $error->rule->name }}
                                            <div class="mt-1">
                                                <span title="Failed runs in the last 24 hours"
                                                    class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400">
                                                    {{ $error->failures }} {{ Str::plural('failure', $error->failures) }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-4 text-sm dark:text-gray-400">
                                            @php $details = $error->result; @endphp

                                            <div class="font-mono text-xs leading-relaxed space-y-2">

                                                @if(!empty($details['exception']))
                                                    <div class="text-red-600 dark:text-red-400">
                                                        <strong>Error:</strong> {{ $details['exception'] }}
                                                    </div>
                                                @endif

                                                @if(!empty($details['message']))
                                                    <div>
                                                        <strong class="text-gray-900 dark:text-gray-200">Message:</strong>
                                                        <span class="text-gray-800 dark:text-gray-300">
                                                            {{ $details['message'] }}
                                                        </span>
                                                    </div>
                                                @endif

                                                @if(!empty($details['row_mismatch']))
                                                    <div>
                                                        <strong class="text-gray-900 dark:text-gray-200">Row Mismatch:</strong><br>
                                                        <span class="text-gray-700 dark:text-gray-300">
                                                            Query: {{ $details['row_mismatch']['query'] }}<br>
                                                            Expected: {{ $details['row_mismatch']['expected'] }}<br>
                                                            Actual: {{ $details['row_mismatch']['actual'] }}
                                                        </span>
                                                    </div>
                                                    {{-- Blade comment (not an HTML one) so the sample rows are neither looped over nor sent to the browser.
                                                    <div class="mt-2">
                                                        <span class="font-semibold">Rows:</span>
                                                        <ul class="list-disc ml-5 text-sm">
                                                            @foreach($details['row_mismatch']['result'] as $row)
                                                                <li class="p-2 bg-gray-50 rounded text-xs overflow-x-auto">
                                                                    <pre class="whitespace-pre-wrap break-all">{{ json_encode($row) }}</pre>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div> --}}
                                                @endif

                                                @if(!empty($details['data_mismatch']))
                                                    <div>
                                                        <strong class="text-gray-900 dark:text-gray-200">Data Mismatch:</strong>
                                                        <ul class="list-disc ml-4 text-gray-700 dark:text-gray-300">
                                                            @foreach($details['data_mismatch'] as $rowIndex => $fields)
                                                            <li>
                                                                <strong class="text-gray-900 dark:text-gray-200">Row
                                                                    {{ $rowIndex }}:</strong>
                                                                <ul class="list-disc ml-4">
                                                                    @foreach($fields as $field => $pair)
                                                                    <li>{{ $field }} → expected {{ $pair['expected'] }}, got
                                                                        {{ $pair['actual'] }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                @if(empty($details['exception']) && empty($details['row_mismatch']) &&
                                                empty($details['data_mismatch']) && empty($details['message']))
                                                    <span class="text-gray-500 dark:text-gray-400 italic">
                                                        No additional failure details were recorded.
                                                    </span>
                                                @endif

                                            </div>
                                        </td>

                                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            {{ $error->created_at->diffForHumans() }}
                                        </td>

                                        <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6">
                                            <a href="{{ route('debug-monitor.rules.show', $error->debug_rule_id) }}"
                                                class="px-2 py-1 rounded text-white text-sm bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-600 dark:text-gray-400">
                                            No errors in the last 24 hours.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if($recentErrors->hasPages())
                            <div class="p-3 dark:text-gray-300">
                                {{ $recentErrors->links('pagination::tailwind') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

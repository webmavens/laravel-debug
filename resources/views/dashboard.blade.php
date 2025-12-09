@extends('debug-monitor::layouts.app')

@section('content')

<div class="relative isolate overflow-hidden mt-[90px]">
    <div
        class="border-b border-b-gray-900/10 lg:border-t lg:border-t-gray-900/5 dark:border-b-white/10 dark:lg:border-t-white/5">
        <dl class="mx-auto grid max-w-7xl grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 lg:px-2 xl:px-0">
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-t border-gray-900/5 px-4 py-10 sm:px-6 lg:border-t-0 xl:px-8 dark:border-white/5">
                <dt class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Total Rules</dt>
                <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 dark:text-white">
                    {{ $totalRules }}</dd>
            </div>
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-t border-gray-900/5 px-4 py-10 sm:border-l sm:px-6 lg:border-t-0 xl:px-8 dark:border-white/5">
                <dt class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Active Rules</dt>
                <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 dark:text-white">
                    {{ $activeRules }}</dd>
            </div>
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-t border-gray-900/5 px-4 py-10 sm:px-6 lg:border-t-0 lg:border-l xl:px-8 dark:border-white/5">
                <dt class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Inactive Rules</dt>
                <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 dark:text-white">
                    {{ $inactiveRules }}</dd>
            </div>
            <div
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-t border-gray-900/5 px-4 py-10 sm:border-l sm:px-6 lg:border-t-0 xl:px-8 dark:border-white/5">
                <dt class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Suppressed Rules</dt>
                <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 dark:text-white">
                    {{ $suppressRules }}</dd>
            </div>
        </dl>
    </div>

    <div aria-hidden="true"
        class="absolute top-full left-0 -z-10 mt-96 origin-top-left translate-y-40 -rotate-90 transform-gpu opacity-20 blur-3xl sm:left-1/2 sm:-mt-10 sm:-ml-96 sm:translate-y-0 sm:rotate-0 sm:opacity-50 dark:opacity-10 dark:sm:opacity-30">
        <div style="clip-path: polygon(100% 38.5%, 82.6% 100%, 60.2% 37.7%, 52.4% 32.1%, 47.5% 41.8%, 45.2% 65.6%, 27.5% 23.4%, 0.1% 35.3%, 17.9% 0%, 27.7% 23.4%, 76.2% 2.5%, 74.2% 56%, 100% 38.5%)"
            class="aspect-1154/678 w-288.5 bg-linear-to-br from-[#FF80B5] to-[#9089FC]"></div>
    </div>
</div>

<div class="space-y-16 py-16 xl:space-y-20">

    <div class="border-t border-gray-200 pt-11">
        <h2 class="px-4 text-base/7 font-semibold text-gray-900 sm:px-6 lg:px-8">Recent Errors (Last 24 Hours)</h2>
        <table class="mt-6 w-full text-left">
            <colgroup>
                <col class="w-full sm:w-4/12" />
                <col class="lg:w-4/12" />
                <col class="lg:w-2/12" />
                <col class="lg:w-1/12" />
                <col class="lg:w-1/12" />
            </colgroup>
            <thead class="border-b border-gray-200 text-sm/6 text-gray-900">
                <tr>
                    <th scope="col" class="py-2 pr-8 pl-4 font-semibold sm:pl-6 lg:pl-8">Rule</th>
                    <th scope="col" class="hidden py-2 pr-8 pl-0 font-semibold sm:table-cell">Result</th>
                    <th scope="col"
                        class="hidden py-2 pr-4 pl-0 text-right font-semibold sm:table-cell sm:pr-6 lg:pr-8">
                        Run at</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentErrors as $error)
                    <tr>
                        <td class="py-4 pr-8 pl-4 sm:pl-6 lg:pl-8">
                            <div class="flex items-center gap-x-4">
                                <div class="truncate text-sm/6 font-medium text-gray-900">{{ $error->rule->name }}</div>
                            </div>
                        </td>
                        <td class="py-4 pr-4 pl-0 sm:table-cell sm:pr-8 text-gray-600">
                            <div class="flex gap-x-3">
                                <div class="font-mono text-sm/6">
                                    @php
                                        $details = $error->result;
                                    @endphp

                                    {{-- Exception --}}
                                    @if(!empty($details['exception']))
                                        <div class="text-red-600">
                                            <strong>Error:</strong> {{ $details['exception'] }}
                                        </div>
                                    @endif

                                    {{-- Simple message --}}
                                    @if(!empty($details['message']))
                                        <div class="mb-1">
                                            <span class="font-medium">Message:</span>
                                            {{ $details['message'] }}
                                        </div>
                                    @endif

                                    {{-- Row mismatch --}}
                                    @if(!empty($details['row_mismatch']))
                                        <div class="mt-2">
                                            <span class="font-medium">Row Mismatch:</span>
                                            <div class="text-sm mt-1">
                                                <strong>Query:</strong> {{ $details['row_mismatch']['query'] }}<br>
                                                <strong>Expected:</strong> {{ $details['row_mismatch']['expected'] }}<br>
                                                <strong>Actual:</strong> {{ $details['row_mismatch']['actual'] }}
                                            </div>

                                            {{-- Show the rows --}}
                                            <div class="mt-2">
                                                <span class="font-semibold">Rows:</span>
                                                <ul class="list-disc ml-5 text-sm">
                                                    @foreach($details['row_mismatch']['result'] as $row)
                                                        <li class="p-2 bg-gray-50 rounded text-xs overflow-x-auto">
                                                            <pre class="whitespace-pre-wrap break-all">{{ json_encode($row) }}</pre>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Data mismatch --}}
                                    @if(!empty($details['data_mismatch']))
                                        <div class="mt-2">
                                            <span class="font-medium">Data Mismatches:</span>
                                            <ul class="list-disc ml-5 text-sm">
                                                @foreach($details['data_mismatch'] as $rowIndex => $fields)
                                                <li class="mt-1">
                                                    <strong>Row {{ $rowIndex }}:</strong>
                                                    <ul class="list-disc ml-5">
                                                        @foreach($fields as $field => $pair)
                                                        <li>
                                                            <strong>{{ $field }}:</strong>
                                                            expected <strong>{{ $pair['expected'] }}</strong>,
                                                            got <strong>{{ $pair['actual'] }}</strong>
                                                        </li>
                                                        @endforeach
                                                    </ul>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    {{-- Passed case (result is simple rows array) --}}
                                    @if(empty($details['row_mismatch']) && empty($details['data_mismatch']) &&
                                    empty($details['exception']) && empty($details['message']))
                                        <div class="text-sm text-gray-600">
                                            Returned {{ count($details) }} row(s).
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-4 pr-4 pl-0 text-right text-sm/6 text-gray-600 sm:table-cell sm:pr-6 lg:pr-8">
                            <time datetime="2023-01-16T15:54">{{ $error->created_at->diffForHumans() }}</time>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-4 text-center text-gray-600">
                            No errors in the last 24 hours.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl lg:mx-0 lg:max-w-none">
            <div class="flex items-center justify-between">
                <h2 class="text-base/7 font-semibold text-gray-900 dark:text-white">Frequency & Run Queue</h2>
            </div>
            <ul role="list" class="mt-6 grid grid-cols-1 gap-x-6 gap-y-8 lg:grid-cols-3 xl:gap-x-8">
                <li
                    class="overflow-hidden rounded-xl outline outline-gray-200 dark:-outline-offset-1 dark:outline-white/10">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6 dark:border-white/10 dark:bg-gray-800/50">
                        <div class="text-sm/6 font-medium text-gray-900 dark:text-white">Runs in the next 1 minute</div>
                    </div>
                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6 dark:divide-white/10">
                        <div class="py-3">
                            @if(count($runsNext1))
                            @foreach($runsNext1 as $item)
                            <dt class="text-gray-500 dark:text-gray-400"><strong>{{ $item['rule']->name }}</strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}</dt>
                            @endforeach
                            @else
                            <p class="text-gray-500 text-sm">No rules running in next 1 minute.</p>
                            @endif
                        </div>
                    </dl>
                </li>
                <li
                    class="overflow-hidden rounded-xl outline outline-gray-200 dark:-outline-offset-1 dark:outline-white/10">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6 dark:border-white/10 dark:bg-gray-800/50">
                        <div class="text-sm/6 font-medium text-gray-900 dark:text-white">Runs in the next 5 minutes
                        </div>
                    </div>
                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6 dark:divide-white/10">
                        <div class="py-3">
                            @if(count($runsNext5))
                            @foreach($runsNext5 as $item)
                            <dt class="text-gray-500 dark:text-gray-400"><strong>{{ $item['rule']->name }}</strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}</dt>
                            @endforeach
                            @else
                            <p class="text-gray-500 text-sm">No rules running in next 5 minutes.</p>
                            @endif
                        </div>
                    </dl>
                </li>
                <li
                    class="overflow-hidden rounded-xl outline outline-gray-200 dark:-outline-offset-1 dark:outline-white/10">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6 dark:border-white/10 dark:bg-gray-800/50">
                        <div class="text-sm/6 font-medium text-gray-900 dark:text-white">Runs in the next 15 minutes
                        </div>
                    </div>
                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6 dark:divide-white/10">
                        <div class="py-3">
                            @if(count($runsNext15))
                            @foreach($runsNext15 as $item)
                            <dt class="text-gray-500 dark:text-gray-400"><strong>{{ $item['rule']->name }}</strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}</dt>
                            @endforeach
                            @else
                            <p class="text-gray-500 text-sm">No rules running in next 5 minutes.</p>
                            @endif
                        </div>
                    </dl>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection

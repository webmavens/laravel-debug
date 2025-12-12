@extends('debug-monitor::layouts.app')

@section('content')

<div class="relative isolate overflow-hidden mt-[120px] mb-10">
    <dl class="mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 px-4 sm:px-6 lg:px-8">

        <!-- Card -->
        <div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Rules</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $totalRules }}
            </dd>
        </div>

        <!-- Card -->
        <div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Rules</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $activeRules }}
            </dd>
        </div>

        <!-- Card -->
        <div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive Rules</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $inactiveRules }}
            </dd>
        </div>

        <!-- Card -->
        <div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Suppressed Rules</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $suppressRules }}
            </dd>
        </div>

    </dl>

    <div
        class="mt-11 mx-auto rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">

        <h2 class="p-4 text-base font-semibold text-gray-900 dark:text-gray-100 sm:px-6 lg:px-8">
            Recent Errors (Last 24 Hours)
        </h2>

        <div class="overflow-hidden border-t border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/70">
                    <tr class="text-gray-900 dark:text-gray-100">
                        <th class="py-3.5 px-4 font-semibold sm:px-6 lg:pl-8">Rule</th>
                        <th class="py-3.5 px-4 font-semibold">Result</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Run at</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($recentErrors as $error)
                    <tr class="hover:bg-gray-100 dark:hover:bg-gray-800/40 transition-colors">
                        <td class="py-4 px-4 sm:pl-6 lg:pl-8 truncate font-medium text-gray-900 dark:text-gray-100">
                            {{ $error->rule->name }}
                        </td>

                        <!-- RESULT COLUMN -->
                        <td class="py-4 px-4 text-gray-700 dark:text-gray-300">
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
                                <!-- <div class="mt-2">
                                    <span class="font-semibold">Rows:</span>
                                    <ul class="list-disc ml-5 text-sm">
                                        @foreach($details['row_mismatch']['result'] as $row)
                                            <li class="p-2 bg-gray-50 rounded text-xs overflow-x-auto">
                                                <pre class="whitespace-pre-wrap break-all">{{ json_encode($row) }}</pre>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div> -->
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
                                <span class="text-gray-800 dark:text-gray-300">
                                    Returned {{ count($details) }} row(s).
                                </span>
                                @endif

                            </div>
                        </td>

                        <td class="truncate py-4 px-4 text-right text-gray-600 dark:text-gray-400">
                            {{ $error->created_at->diffForHumans() }}
                        </td>

                        <td class="py-4 px-4 text-right">
                            <a href="{{ route('debug-monitor.rules.show', $error->debug_rule_id) }}"
                                class="px-2 py-1 rounded text-white text-sm bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600">
                                View
                            </a>
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
        </div>

    </div>

    <div class="mt-20 mx-auto pb-8 bg-white dark:bg-gray-900 rounded-xl">
        <div class="mx-auto px-4 lg:mx-0 ">

            <div class="flex items-center justify-between">
                <h2 class="p-4 text-base font-semibold text-gray-900 dark:text-gray-100">
                    Frequency & Run Queue
                </h2>
            </div>

            <ul role="list" class="grid grid-cols-1 gap-x-6 gap-y-8 lg:grid-cols-3 xl:gap-x-8">

                <!-- CARD -->
                <li class="overflow-hidden rounded-xl shadow-lg outline outline-gray-200 dark:outline-gray-700">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 dark:border-white/10 bg-gray-50 dark:bg-gray-800/60 p-6">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            Runs in the next 1 minute
                        </div>
                    </div>

                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 dark:divide-gray-800 px-6 py-4 text-sm">
                        <div class="py-3">
                            @if(count($runsNext1))
                            @foreach($runsNext1 as $item)
                            <dt class="border-b border-gray-200 dark:border-gray-700 py-1 text-gray-600 dark:text-gray-300">
                                <strong class="text-gray-800 dark:text-gray-200">
                                    {{ $item['rule']->name }}
                                </strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}
                            </dt>
                            @endforeach
                            @else
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                No rules running in next 1 minute.
                            </p>
                            @endif
                        </div>
                    </dl>
                </li>

                <!-- CARD -->
                <li class="overflow-hidden rounded-xl shadow-lg outline outline-gray-200 dark:outline-gray-700">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 dark:border-white/10 bg-gray-50 dark:bg-gray-800/60 p-6">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            Runs in the next 5 minutes
                        </div>
                    </div>

                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 dark:divide-gray-800 px-6 py-4 text-sm">
                        <div class="py-3">
                            @if(count($runsNext5))
                            @foreach($runsNext5 as $item)
                            <dt class="border-b border-gray-200 dark:border-gray-700 py-1 text-gray-600 dark:text-gray-300">
                                <strong class="text-gray-800 dark:text-gray-200">
                                    {{ $item['rule']->name }}
                                </strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}
                            </dt>
                            @endforeach
                            @else
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                No rules running in next 5 minutes.
                            </p>
                            @endif
                        </div>
                    </dl>
                </li>

                <!-- CARD -->
                <li class="overflow-hidden rounded-xl shadow-lg outline outline-gray-200 dark:outline-gray-700">
                    <div
                        class="flex items-center gap-x-4 border-b border-gray-900/5 dark:border-white/10 bg-gray-50 dark:bg-gray-800/60 p-6">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            Runs in the next 15 minutes
                        </div>
                    </div>

                    <dl
                        class="overflow-auto h-80 -my-3 divide-y divide-gray-100 dark:divide-gray-800 px-6 py-4 text-sm">
                        <div class="py-3">
                            @if(count($runsNext15))
                            @foreach($runsNext15 as $item)
                            <dt class="border-b border-gray-200 dark:border-gray-700 py-1 text-gray-600 dark:text-gray-300">
                                <strong class="text-gray-800 dark:text-gray-200">
                                    {{ $item['rule']->name }}
                                </strong>
                                — Next at: {{ $item['nextRun']->format('h:i:s A') }}
                            </dt>
                            @endforeach
                  
                            @else
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                No rules running in next 15 minutes.
                            </p>
                            @endif
                        </div>
                    </dl>
                </li>

            </ul>
        </div>
    </div>
</div>
@endsection

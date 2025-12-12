@extends('debug-monitor::layouts.app')

@section('content')
<div class="bg-white dark:bg-gray-900 shadow rounded-lg p-6 mt-[90px]">

    {{-- Go Back --}}
    <a href="{{ route('debug-monitor.rules.index') }}"
       class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 text-lg mr-5">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none"
             viewBox="0 0 24 24" stroke="currentColor"
             class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16l-4-4m0 0l4-4m-4 4h18"></path>
        </svg>
        <span class="ml-1 font-semibold underline">Go Back</span>
    </a>

    {{-- Rule Info --}}
    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300 mt-6">
        <p class="text-xl font-bold mb-2">
            Rule Name: <span class="font-normal">{{ $rule->name }}</span>
        </p>
        <p><strong>SQL:</strong> {{ $rule->sql_query }}</p>
        <p><strong>Expected Rows:</strong> {{ $rule->expected_rows_operator }} {{ $rule->expected_rows }}</p>
        <p><strong>Importance:</strong> {{ ucfirst($rule->importance_level) }}</p>
        <p><strong>Frequency:</strong> {{ $rule->frequency_minutes }} min</p>
        <p><strong>Status:</strong> {{ ucfirst($rule->status) }}</p>
        <p><strong>Last Run:</strong> {{ optional($rule->last_run_at)->toDayDateTimeString() }}</p>

        @if ($rule->last_debug_log)
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-2 dark:text-gray-200">Last Debug Log</h3>

                <pre class="shadow-sm rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-sm overflow-x-auto text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
{{ json_encode($rule->last_debug_log, JSON_PRETTY_PRINT) }}
                </pre>
            </div>
        @endif
    </div>

    <div class="mt-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Logs</h1>
            </div>
        </div>

        <div class="mt-6 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">

                    <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">
                                    Time
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Status
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Result
                                </th>
                                <th class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pr-6">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">

                            @forelse($logs as $log)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">

                                    <td class="py-4 pl-4 pr-3 text-sm whitespace-nowrap text-gray-900 dark:text-gray-100 sm:pl-6">
                                        {{ $log->created_at->diffForHumans() }}
                                    </td>

                                    <td class="px-3 py-4 text-sm font-medium whitespace-nowrap
                                        {{ $log->status === 'ok' ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                        {{ ucfirst($log->status) }}
                                    </td>

                                    <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300 max-w-[400px] whitespace-pre-wrap overflow-x-auto">
<pre class="text-xs whitespace-pre-wrap">{{ json_encode($log->result, JSON_PRETTY_PRINT) }}</pre>
                                    </td>

                                    <td class="py-4 pl-3 pr-4 text-right text-sm sm:pr-6">
                                        <form action="{{ route('debug-monitor.logs.destroy', $log) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button
                                                class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    </td>

                                </tr>

                            @empty
                                <tr>
                                    <td colspan="4"
                                        class="px-4 py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                        No logs found.
                                    </td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>

    <div class="mt-10">
        <h3 class="text-lg font-semibold mb-2 dark:text-gray-100">Actions</h3>

        <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}"
              method="POST"
              class="space-y-3">
            @csrf

            <div>
                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Notes (for suppress)</label>

                <textarea name="suppress_notes"
                          class="mt-2 block w-full rounded-lg bg-white dark:bg-gray-800 px-3 py-2
                                 text-sm text-gray-900 dark:text-gray-100 outline-1 outline-gray-300
                                 dark:outline-gray-700 placeholder:text-gray-400
                                 focus:outline-2 focus:outline-indigo-600"
                          rows="3">{{ $rule->suppress_notes }}</textarea>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    ⓘ Suppress means rule still runs, but no alerts are sent.
                </p>
            </div>

            <input type="hidden" name="action" value="{{ $rule->suppress ? 'unsuppress' : 'suppress' }}">

            <button
                class="px-4 py-2 rounded text-white cursor-pointer
                       {{ $rule->suppress
                            ? 'bg-blue-600 hover:bg-blue-700'
                            : 'bg-yellow-500 hover:bg-yellow-600'
                       }}">
                {{ $rule->suppress ? 'Unsuppress' : 'Suppress' }}
            </button>

        </form>
    </div>

</div>
@endsection

@extends('debug-monitor::layouts.app')

@section('content')

<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Rule: {{ $rule->name }}</h1>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <div class="flex items-center gap-2">
            <a href="{{ route('debug-monitor.rules.index') }}"
                class="text-sm flex items-center gap-1 text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 18h3.75a5.25 5.25 0 100-10.5H5M7.5 4L4 7.5 7.5 11"></path>
                </svg>
                Go Back
            </a>
        </div>
    </div>
</div>
<div class="mt-8">
    <div
        class="bg-white shadow-sm outline outline-1 outline-gray-900/5 sm:rounded-xl md:col-span-2 dark:bg-gray-800/50 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
        <div class="px-4 py-6 sm:p-8">
            <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <p><strong>Expected Rows:</strong> {{ $rule->expected_rows_operator }} {{ $rule->expected_rows }}
                </p>
                <p><strong>Importance:</strong> {{ ucfirst($rule->importance_level) }}</p>
                <p><strong>Frequency:</strong> {{ $rule->frequency_minutes }} min</p>
                <p><strong>Status:</strong> {{ ucfirst($rule->status) }}</p>
                <p><strong>Last Run:</strong> {{ optional($rule->last_run_at)->diffForHumans() }}</p>
                <p><strong>SQL:</strong> <code>{{ $rule->sql_query }}</code></p>
            </div>
            @if ($rule->last_debug_log)
            <div class="mt-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-2">Last Debug Log</h3>

                <pre
                    class="shadow-sm rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs overflow-x-auto text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ json_encode($rule->last_debug_log, JSON_PRETTY_PRINT) }}
                    </pre>
            </div>
            @endif

            <div class="mt-8">
                <div class="sm:flex sm:items-center">
                    <div class="sm:flex-auto">
                        <h1 class="text-base font-semibold text-gray-900 dark:text-white">Recent Logs</h1>
                    </div>
                </div>
                <div class="mt-5 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                            <div
                                class="border border-gray-900/10 dark:border-white/10 overflow-hidden shadow-sm outline-1 outline-black/5 sm:rounded-lg dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                                    <thead class="bg-gray-50 dark:bg-gray-800/75">
                                        <tr>
                                            <th scope="col"
                                                class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 dark:text-gray-200">
                                                Time</th>
                                            <th scope="col"
                                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                                Status</th>
                                            <th scope="col"
                                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                                Result</th>
                                            <th scope="col"
                                                class="py-3.5 pr-4 pl-3 sm:pr-6 text-right text-sm font-semibold text-gray-900 dark:text-gray-200">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-800/50">
                                        @forelse($logs as $log)
                                        <tr>
                                            <td
                                                class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-6 dark:text-white">
                                                {{ $log->created_at->diffForHumans() }}</td>
                                            <td
                                                class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                <span
                                                    class="inline-flex items-center rounded-md  px-2 py-1 text-xs font-medium {{ $log->status == 'ok' ? 'bg-green-100 text-green-700 dark:bg-green-400/10 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-400' }}">{{ ucfirst($log->status) }}</span>
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                {{ json_encode($log->result, JSON_PRETTY_PRINT) }}
                                            </td>
                                            <td
                                                class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6">
                                                <form action="{{ route('debug-monitor.logs.destroy', $log) }}"
                                                    method="POST">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-sm bg-red-600 px-2 py-1 text-xs font-semibold text-white shadow-xs hover:bg-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400 dark:focus-visible:outline-red-500">Delete</button>
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
            </div>

        </div>

        <!-- Footer -->
        <div class="border-t border-gray-900/10 px-4 py-4 sm:px-8 dark:border-white/10">

            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Actions</h2>
            <div class="mt-5 col-span-full">
                <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}" method="POST">
                    @csrf
                    <label for="suppress_notes" class="block text-sm leading-6 font-medium text-gray-900 dark:text-white">
                        Notes (for suppress)
                    </label>
                    <div class="mt-2">
                        <textarea id="suppress_notes" name="suppress_notes" rows="3"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900
                                outline outline-1 -outline-offset-1 outline-gray-300
                                placeholder:text-gray-400
                                focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600
                                sm:text-sm sm:leading-6
                                dark:bg-white/5 dark:text-white
                                dark:outline dark:outline-1 dark:-outline-offset-1 dark:outline-gray-600
                                dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">{{ $rule->suppress_notes }}</textarea>
                    </div>
                    <p class="mt-2 text-xs leading-6 text-gray-600 dark:text-gray-400">
                        Suppress means rule still runs, but no alerts are sent.
                    </p>
                    <div class="mt-5">
                        <input type="hidden" name="action"
                            value="{{ $rule->suppress ? 'unsuppress' : 'suppress' }}">
                        <button type="submit" id="submit-btn"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm
                            hover:bg-indigo-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600
                            dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus:outline-indigo-500">
                            {{ $rule->suppress ? 'Unsuppress' : 'Suppress' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

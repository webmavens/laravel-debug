@extends('debug-monitor::layouts.app')

@section('content')
<div class="bg-white shadow rounded p-6 mt-[90px]">
    <a href="{{ route('debug-monitor.rules.index') }}"
        class="inline-flex items-center text-indigo-600 hover:text-indigo-700 text-lg mr-5">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18">
            </path>
        </svg>
        <span class="ml-1 font-bold underline">Go Back</span>
    </a>

    <div class="space-y-1 text-sm text-gray-700 mt-5">
        <p class="text-xl mb-2"><strong>Rule Name:</strong> {{ $rule->name }}</p>
        <p><strong>SQL:</strong> {{ $rule->sql_query }}</p>
        <p><strong>Expected Rows:</strong> {{ $rule->expected_rows_operator }} {{ $rule->expected_rows }}</p>
        <p><strong>Importance:</strong> {{ ucfirst($rule->importance_level) }}</p>
        <p><strong>Frequency:</strong> {{ $rule->frequency_minutes }} min</p>
        <p><strong>Status:</strong> {{ ucfirst($rule->status) }}</p>
        <p><strong>Last Run:</strong> {{ optional($rule->last_run_at)->toDayDateTimeString() }}</p>

        @if ($rule->last_debug_log)
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-2">Last Debug Log</h3>
                <pre class="shadow-sm sm:rounded-lg bg-gray-100 p-3 text-sm overflow-x-auto">{{ json_encode($rule->last_debug_log, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </div>

    <div class="mt-6">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-base text-lg font-semibold">Recent Logs</h1>
            </div>
        </div>
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <table class="relative min-w-full divide-y divide-gray-300">
                        <thead>
                            <tr class="text-gray-900">
                                <th scope="col"
                                    class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold sm:pl-0">Time</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold">Result</th>
                                <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-0 text-sm">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($logs as $log)
                                <tr>
                                    <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0">{{ $log->created_at->diffForHumans() }}</td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap font-medium {{ $log->status == 'ok' ? 'text-green-500' : 'text-red-500' }}">{{ ucfirst($log->status) }}</td>
                                    <td class="px-3 py-4 text-sm text-gray-600">{{ json_encode($log->result, JSON_PRETTY_PRINT) }}</td>

                                    <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-0">
                                        <form action="{{ route('debug-monitor.logs.destroy', $log) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button class="px-2 py-1 bg-red-500 text-white rounded text-xs cursor-pointer">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <td colspan="4" class="text-gray-600 text-center text-sm">No logs found.</td>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold mb-2">Actions</h3>
        <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block font-medium text-sm text-gray-700">Notes (for suppress)</label>
                <textarea name="suppress_notes" class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" rows="3">{{ $rule->suppress_notes }}</textarea>
                <p class="text-xs text-gray-500 mt-1">ⓘ Suppress means rule still runs, but no alerts are sent.</p>
            </div>

            @if(!$rule->suppress)
                <input type="hidden" name="action" value="suppress">
                <button class="bg-yellow-500 text-white px-4 py-1 rounded hover:bg-yellow-600 cursor-pointer">Suppress</button>
            @else
                <input type="hidden" name="action" value="unsuppress">
                <button class="bg-blue-600 text-white px-4 py-1 rounded hover:bg-blue-700 cursor-pointer">Unsuppress</button>
            @endif
        </form>
    </div>
</div>
@endsection

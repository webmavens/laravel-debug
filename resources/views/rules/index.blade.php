@extends('debug-monitor::layouts.app')

@section('content')

<div class="px-4 sm:px-6 lg:px-8 mt-[95px]">
    <div class="sm:flex sm:items-center">

        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold text-gray-900 dark:text-gray-100">All Rules</h1>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">A list of all the rules</p>
        </div>

        <div>
            <form method="GET" id="filter" class="mb-4">
                <div class="flex items-center relative">
                    <label for="importance_level" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mr-2">
                        Importance:
                    </label>

                    <select
                        name="importance_level"
                        id="importance_level"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white dark:bg-gray-800 py-1.5 pr-8 pl-3 text-base text-gray-900 dark:text-gray-100 outline-1 -outline-offset-1 outline-gray-300 dark:outline-gray-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm"
                        onchange="document.getElementById('filter').submit()"
                    >
                        @foreach(['all', 'high', 'medium', 'low'] as $level)
                            <option value="{{ $level }}" @selected(request('importance_level') == $level)">
                                {{ ucfirst($level) }}
                            </option>
                        @endforeach
                    </select>

                    <svg
                        viewBox="0 0 16 16"
                        fill="currentColor"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-500 dark:text-gray-400 sm:size-4"
                    >
                        <path
                            d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                        />
                    </svg>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-8 flow-root">
        <div class="-mx-4 my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle">

                <table class="relative min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th scope="col"
                                class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">
                                Name
                            </th>

                            <th scope="col"
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Query
                            </th>

                            <th scope="col"
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Status
                            </th>

                            <th scope="col"
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Last Run
                            </th>

                            <th scope="col"
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Suppress
                                <span class="ml-1 cursor-pointer text-gray-400 dark:text-gray-500"
                                    title="Suppress means this rule will still run and log results, but no email/alert will be sent.">
                                    ⓘ
                                </span>
                            </th>

                            <th scope="col"
                                class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pr-6">
                                Actions
                            </th>

                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($rules as $rule)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">

                                <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-gray-100 sm:pl-0">
                                    {{ $rule->name }}
                                </td>

                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-300">
                                    {{ Str::limit($rule->sql_query, 70) }}
                                </td>

                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-300">
                                    {{ ucfirst($rule->status) }}
                                </td>

                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-300">
                                    {{ optional($rule->last_run_at)->diffForHumans() }}
                                </td>

                                <td class="py-4 pr-4 pl-3 text-sm font-medium whitespace-nowrap sm:pr-0">
                                    <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $rule->suppress ? 'unsuppress' : 'suppress' }}">

                                        <button
                                            type="submit"
                                            onclick="return confirm('Are you sure you want to {{ $rule->suppress ? 'unsuppress' : 'suppress' }} this rule?')"
                                            class="px-2 py-0 text-white text-sm rounded cursor-pointer
                                                {{ $rule->suppress
                                                    ? 'bg-blue-600 hover:bg-blue-700'
                                                    : 'bg-yellow-600 hover:bg-yellow-700'
                                                }}"
                                        >
                                            {{ $rule->suppress ? 'Unsuppress' : 'Suppress' }}
                                        </button>
                                    </form>
                                </td>

                                <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-0">
                                    <a href="{{ route('debug-monitor.rules.show', $rule->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 hover:underline">Show</a>&nbsp;

                                    <a href="{{ route('debug-monitor.rules.edit', $rule->id) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 hover:underline">Edit</a>&nbsp;

                                    <form action="{{ route('debug-monitor.rules.destroy', $rule) }}" method="post" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Are you sure?')" class="text-red-600 dark:text-red-400 hover:text-red-500 dark:hover:text-red-300 cursor-pointer hover:underline">
                                            Delete
                                        </button>
                                    </form>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">
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
@extends('debug-monitor::layouts.app')

@section('content')

<div class="sm:flex sm:items-center">
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold text-gray-900 dark:text-white">All Rules</h1>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">A list of all the rules</p>
    </div>
    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <form method="GET" id="filter">
            <div class="flex items-center gap-2">
                <label for="importance_level"
                    class="block text-sm/6 font-medium text-gray-900 dark:text-white">Importance</label>
                <div class="grid grid-cols-1">
                    <select name="importance_level" id="importance_level"
                        class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:*:bg-gray-800 dark:focus-visible:outline-indigo-500"
                        onchange="document.getElementById('filter').submit()">

                        @foreach(['all', 'high', 'medium', 'low'] as $level)
                            <option value="{{ $level }}" @selected(request('importance_level')==$level)>
                                {{ ucfirst($level) }}
                            </option>
                        @endforeach
                    </select>
                    <svg viewBox="0 0 16 16" fill="currentColor" data-slot="icon" aria-hidden="true"
                        class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                        <path
                            d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z"
                            clip-rule="evenodd" fill-rule="evenodd" />
                    </svg>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mt-8 flow-root">
    <div class="-my-2 overflow-hidden">
        <div class="inline-block w-full py-2 align-middle">
            <div
                class="overflow-hidden shadow-sm outline-1 outline-black/5 sm:rounded-lg dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                
                <table class="relative w-full table-fixed divide-y divide-gray-300 dark:divide-white/15">
                    <thead class="bg-white dark:bg-gray-800/75">
                        <tr>
                            <th scope="col"
                                class="w-2/5 sm:w-1/4 py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 dark:text-gray-200">
                                Name</th>
                            <th scope="col"
                                class="w-2/5 sm:w-1/3 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                Query
                            </th>
                            <th scope="col"
                                class="w-1/12 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                Status
                            </th>
                            <th scope="col"
                                class="w-1/12 px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-200">
                                Last Run
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
                                class="w-32 py-3.5 pr-4 pl-3 text-sm font-semibold text-gray-900 dark:text-gray-200 text-right sm:pr-6">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-800/50">
                        @forelse($rules as $rule)
                            <tr>
                                <td
                                    class="py-4 pr-3 pl-4 text-sm font-medium whitespace-normal break-words truncate sm:pl-6 dark:text-white">
                                    {{ $rule->name }}</td>
                                <td class="px-3 py-4 text-sm whitespace-normal break-words max-w-[220px] sm:max-w-[520px] text-gray-500 dark:text-gray-400">
                                    {{ Str::limit($rule->sql_query, 70) }}</td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    <span
                                        class="inline-flex items-center rounded-md  px-2 py-1 text-xs font-medium {{ $rule->status == 'active' ? 'bg-green-100 text-green-700 dark:bg-green-400/10 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-400' }}">{{ ucfirst($rule->status) }}</span>
                                </td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ optional($rule->last_run_at)->diffForHumans() }}
                                </td>
                                <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    <form action="{{ route('debug-monitor.rules.suppress', $rule->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        <input type="hidden" name="action"
                                            value="{{ $rule->suppress ? 'unsuppress' : 'suppress' }}">
                                        <button type="submit"
                                            class="rounded-sm bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
                                            onclick="return confirm('Are you sure you want to {{ $rule->suppress ? 'unsuppress' : 'suppress' }} this rule?')">{{ $rule->suppress ? 'Unsuppress' : 'Suppress' }}</button>
                                    </form>
                                </td>
                                <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-6">
                                    <span class="flex gap-2">
                                        <a href="{{ route('debug-monitor.rules.show', $rule->id) }}"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Show</a>
                                        <a href="{{ route('debug-monitor.rules.edit', $rule->id) }}"
                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">Edit</a>
                                        <form action="{{ route('debug-monitor.rules.destroy', $rule) }}" method="post"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" onclick="return confirm('Are you sure?')"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                Delete
                                            </button>
                                        </form>
                                    </span>

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

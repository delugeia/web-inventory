@extends('layouts.app')

@section('content_max_width', 'max-w-screen-2xl')

@section('content')
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Endpoints</h1>
            <p class="mt-1 text-sm text-slate-600">Manage monitored locations and URLs.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="{{ route('endpoints.import') }}">Bulk Import</a>
            <a class="inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" href="{{ route('endpoints.create') }}">Create</a>
        </div>
    </div>

    <form class="mb-6 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 lg:flex-row lg:items-end lg:gap-4" method="GET" action="{{ route('endpoints.index') }}">
        <div class="flex-1">
            <label class="block text-sm font-medium text-slate-700" for="q">Search</label>
            <input class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" id="q" name="q" type="text" value="{{ $search }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="per_page">Rows</label>
            <select class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900" id="per_page" name="per_page">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <button class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Apply</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="px-4 py-3 font-medium">
                        <a class="inline-flex items-center gap-1 hover:text-slate-900" href="{{ route('endpoints.index', ['q' => $search !== '' ? $search : null, 'per_page' => $perPage, 'sort' => 'location', 'direction' => $sort === 'location' && $direction === 'asc' ? 'desc' : 'asc']) }}">Location</a>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <a class="inline-flex items-center gap-1 hover:text-slate-900" href="{{ route('endpoints.index', ['q' => $search !== '' ? $search : null, 'per_page' => $perPage, 'sort' => 'name', 'direction' => $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc']) }}">Name</a>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <a class="inline-flex items-center gap-1 hover:text-slate-900" href="{{ route('endpoints.index', ['q' => $search !== '' ? $search : null, 'per_page' => $perPage, 'sort' => 'resolved_url', 'direction' => $sort === 'resolved_url' && $direction === 'asc' ? 'desc' : 'asc']) }}">Resolved URL</a>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <a class="inline-flex items-center gap-1 hover:text-slate-900" href="{{ route('endpoints.index', ['q' => $search !== '' ? $search : null, 'per_page' => $perPage, 'sort' => 'last_status_code', 'direction' => $sort === 'last_status_code' && $direction === 'asc' ? 'desc' : 'asc']) }}">Last Status Code</a>
                    </th>
                    <th class="px-4 py-3 font-medium">
                        <a class="inline-flex items-center gap-1 hover:text-slate-900" href="{{ route('endpoints.index', ['q' => $search !== '' ? $search : null, 'per_page' => $perPage, 'sort' => 'last_checked_at', 'direction' => $sort === 'last_checked_at' && $direction === 'asc' ? 'desc' : 'asc']) }}">Last Checked At</a>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($endpoints as $endpoint)
                    @php($detailsUrl = route('endpoints.show', $endpoint))
                    <tr class="group transition hover:bg-slate-100">
                        <td class="p-0">
                            <a class="block px-4 py-3 text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-inset" href="{{ $detailsUrl }}" aria-label="Open endpoint {{ $endpoint->location }}">
                            <div class="flex items-center gap-2">
                                <span>{{ $endpoint->location }}</span>
                                <span class="text-slate-400 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100" aria-hidden="true">&rarr;</span>
                            </div>
                            </a>
                        </td>
                        <td class="p-0">
                            <a class="block px-4 py-3 text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-inset" href="{{ $detailsUrl }}">{{ $endpoint->name }}</a>
                        </td>
                        <td class="p-0">
                            <a class="block px-4 py-3 text-slate-700 break-all focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-inset" href="{{ $detailsUrl }}">
                                @if ($endpoint->resolved_url)
                                    @if ($endpoint->redirect_followed)
                                        <span class="text-slate-500">&rarrhk;</span>
                                    @endif
                                    {{ $endpoint->resolved_url }}
                                @else
                                    --
                                @endif
                            </a>
                        </td>
                        <td class="p-0">
                            <a class="block px-4 py-3 text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-inset" href="{{ $detailsUrl }}">{{ $endpoint->last_status_code }}</a>
                        </td>
                        <td class="p-0">
                            <a class="block px-4 py-3 text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-inset" href="{{ $detailsUrl }}">{{ $endpoint->last_checked_at }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-slate-500" colspan="5">No endpoints yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $endpoints->links() }}
    </div>
@endsection

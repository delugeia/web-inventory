@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Endpoints</h1>
        <a class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" href="{{ route('endpoints.create') }}">Create</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Location</th>
                    <th class="px-4 py-3 font-medium">Last Status Code</th>
                    <th class="px-4 py-3 font-medium">Last Checked At</th>
                    <th class="px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($endpoints as $endpoint)
                    <tr>
                        <td class="px-4 py-3">{{ $endpoint->name }}</td>
                        <td class="px-4 py-3">{{ $endpoint->location }}</td>
                        <td class="px-4 py-3">{{ $endpoint->last_status_code }}</td>
                        <td class="px-4 py-3">{{ $endpoint->last_checked_at }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" href="{{ route('endpoints.edit', $endpoint) }}">Edit</a>
                                <form method="POST" action="{{ route('endpoints.destroy', $endpoint) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50" type="submit">Delete</button>
                                </form>
                            </div>
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

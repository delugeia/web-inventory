@extends('layouts.app')

@section('content')
    <div x-data="{ confirmDelete: false }" class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <a class="text-sm text-slate-600 underline hover:text-slate-900" href="{{ route('endpoints.index') }}">Back to Endpoints</a>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $endpoint->name ?: $endpoint->location }}</h1>
                @if ($endpoint->name)
                    <p class="mt-1 text-sm text-slate-600">{{ $endpoint->location }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a class="inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" href="{{ route('endpoints.edit', $endpoint) }}">Edit</a>
                <a class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="{{ route('endpoints.resolve', $endpoint) }}">Resolve</a>
                <button class="inline-flex items-center justify-center rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" type="button" @click="confirmDelete = true">Delete</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <dl class="divide-y divide-slate-200">
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">ID</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->id }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Location</dt>
                    <dd class="font-mono text-sm text-slate-900 break-all sm:col-span-2">{{ $endpoint->location }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Name</dt>
                    <dd class="text-sm text-slate-900 sm:col-span-2">{{ $endpoint->name ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Resolved URL</dt>
                    <dd class="font-mono text-sm text-slate-900 break-all sm:col-span-2">
                        @if ($endpoint->resolved_url)
                            <a class="underline hover:text-slate-700" href="{{ $endpoint->resolved_url }}" target="_blank" rel="noopener noreferrer">{{ $endpoint->resolved_url }}</a>
                        @else
                            --
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Last Status Code</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">
                        @if ($endpoint->last_status_code)
                            {{ $endpoint->last_status_code }} {{ \Symfony\Component\HttpFoundation\Response::$statusTexts[$endpoint->last_status_code] ?? 'Unknown Status' }}
                        @else
                            --
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Last Checked At</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->last_checked_at ? \Illuminate\Support\Carbon::parse($endpoint->last_checked_at)->toDayDateTimeString() : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Failure Reason</dt>
                    <dd class="font-mono text-sm text-slate-900 break-all sm:col-span-2">{{ $endpoint->failure_reason ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Redirect Followed</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->redirect_followed ? 'yes' : 'no' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Redirect Count</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->redirect_count ?? 0 }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Redirect Chain</dt>
                    <dd class="text-sm text-slate-900 sm:col-span-2">
                        @if (is_array($endpoint->redirect_chain) && count($endpoint->redirect_chain) > 0)
                            <ul class="space-y-1">
                                @foreach ($endpoint->redirect_chain as $hop)
                                    <li class="font-mono break-all">
                                        {{ $hop['status_code'] ?? '--' }}
                                        {{ \Symfony\Component\HttpFoundation\Response::$statusTexts[$hop['status_code'] ?? 0] ?? 'Unknown Status' }}
                                        :
                                        {{ $hop['url'] ?? '--' }}
                                        @if (!empty($hop['location']))
                                            -> {{ $hop['location'] }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            --
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Created At</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->created_at?->toDayDateTimeString() ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-slate-700">Updated At</dt>
                    <dd class="font-mono text-sm text-slate-900 sm:col-span-2">{{ $endpoint->updated_at?->toDayDateTimeString() ?: '--' }}</dd>
                </div>
            </dl>
        </div>

        <div
            x-show="confirmDelete"
            x-on:keydown.escape.window="confirmDelete = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
            style="display: none;"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-endpoint-title"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 id="delete-endpoint-title" class="text-lg font-semibold text-slate-900">Delete endpoint?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    This will permanently delete
                    <span class="font-mono text-slate-900">{{ $endpoint->location }}</span>.
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="confirmDelete = false">Cancel</button>
                    <form method="POST" action="{{ route('endpoints.destroy', $endpoint) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-md bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700" type="submit">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

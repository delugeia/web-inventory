@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <a class="text-sm text-slate-600 underline hover:text-slate-900" href="{{ route('endpoints.show', $endpoint) }}">Back to Endpoint Details</a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resolve Endpoint</h1>
            <p class="mt-1 text-sm text-slate-600">Current resolver result for this endpoint.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                <p><span class="font-medium text-slate-900">Resolved URL:</span> <span class="font-mono text-slate-700 break-all">{{ $endpoint->resolved_url ?: '--' }}</span></p>
                <p class="mt-1">
                    <span class="font-medium text-slate-900">Last Status Code:</span>
                    <span class="font-mono text-slate-700">
                        @if ($endpoint->last_status_code)
                            {{ $endpoint->last_status_code }} {{ \Symfony\Component\HttpFoundation\Response::$statusTexts[$endpoint->last_status_code] ?? 'Unknown Status' }}
                        @else
                            --
                        @endif
                    </span>
                </p>
                <p class="mt-1"><span class="font-medium text-slate-900">Last Checked:</span> <span class="font-mono text-slate-700">{{ $endpoint->last_checked_at ? \Illuminate\Support\Carbon::parse($endpoint->last_checked_at)->toDayDateTimeString() : '--' }}</span></p>
                <p class="mt-1"><span class="font-medium text-slate-900">Failure Reason:</span> <span class="font-mono text-slate-700 break-all">{{ $endpoint->failure_reason ?: '--' }}</span></p>
                <p class="mt-1"><span class="font-medium text-slate-900">Redirect Followed:</span> <span class="font-mono text-slate-700">{{ $endpoint->redirect_followed ? 'yes' : 'no' }}</span></p>
                <p class="mt-1"><span class="font-medium text-slate-900">Redirect Count:</span> <span class="font-mono text-slate-700">{{ $endpoint->redirect_count ?? 0 }}</span></p>
                <div class="mt-2">
                    <p class="font-medium text-slate-900">Redirect Chain:</p>
                    @if (is_array($endpoint->redirect_chain) && count($endpoint->redirect_chain) > 0)
                        <ul class="mt-1 space-y-1">
                            @foreach ($endpoint->redirect_chain as $hop)
                                <li class="font-mono text-slate-700 break-all">
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
                        <p class="mt-1 font-mono text-slate-700">--</p>
                    @endif
                </div>
            </div>

            <div class="mt-6">
                <form method="POST" action="{{ route('endpoints.resolve.store', $endpoint) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Recheck</button>
                </form>
            </div>
        </div>
    </div>
@endsection

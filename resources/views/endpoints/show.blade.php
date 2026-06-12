@extends('layouts.app')

@section('content_max_width', 'max-w-screen-2xl')

@section('content')
    @php
        $statusText = $endpoint->last_status_code
            ? \Symfony\Component\HttpFoundation\Response::$statusTexts[$endpoint->last_status_code] ?? 'Unknown Status'
            : null;
        $canonicalCheck = is_array($endpoint->canonical_url_check) ? $endpoint->canonical_url_check : null;
        $canonicalStatus = $canonicalCheck['status'] ?? null;
        $variants = is_array($canonicalCheck['variants'] ?? null) ? $canonicalCheck['variants'] : [];
        $dnsSummary = is_array($endpoint->dns_summary) ? $endpoint->dns_summary : null;
        $platformHeaders = is_array($endpoint->platform_headers) ? $endpoint->platform_headers : [];
        $securityHeaders = is_array($endpoint->security_headers) ? $endpoint->security_headers : [];
        $boolLabel = fn ($value) => $value === null ? '--' : ($value ? 'yes' : 'no');
        $badgeClass = fn ($status) => match ($status) {
            'pass', 'ok', 'canonical', 'to_canonical', 'forces_https' => 'bg-emerald-100 text-emerald-800',
            'warning', 'keeps_www', 'different_https' => 'bg-amber-100 text-amber-800',
            'fail', 'failed', 'stays_http', 'external_redirect' => 'bg-rose-100 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
        $resultLabel = fn ($result) => match ($result) {
            'canonical' => 'Canonical',
            'to_canonical' => 'To canonical',
            'forces_https' => 'Forces HTTPS',
            'keeps_www' => 'Keeps www',
            'stays_http' => 'Stays HTTP',
            'external_redirect' => 'External redirect',
            'different_https' => 'Different HTTPS',
            'failed' => 'Failed',
            default => $result ? str_replace('_', ' ', ucfirst($result)) : '--',
        };
        $statusLabel = fn ($status) => match ($status) {
            'pass' => 'Pass',
            'warning' => 'Warning',
            'fail' => 'Fail',
            default => 'Unchecked',
        };
    @endphp

    <div x-data="{ confirmDelete: false }" class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a class="text-sm text-slate-600 underline hover:text-slate-900" href="{{ route('endpoints.index') }}">Back to Endpoints</a>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $endpoint->location }}</h1>
                @if ($endpoint->name)
                    <p class="mt-1 text-sm text-slate-600">{{ $endpoint->name }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a class="inline-flex items-center justify-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" href="{{ route('endpoints.edit', $endpoint) }}">Edit</a>
                <form method="POST" action="{{ route('endpoints.resolve.store', $endpoint) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">Recheck</button>
                </form>
                <button class="inline-flex items-center justify-center rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" type="button" @click="confirmDelete = true">Delete</button>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Endpoint Details</h2>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $endpoint->failure_reason ? 'bg-rose-100 text-rose-800' : ($endpoint->resolved_url ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">
                    {{ $endpoint->failure_reason ? 'Unresolved' : ($endpoint->resolved_url ? 'Resolved' : 'Unchecked') }}
                </span>
            </div>

            <dl>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Location</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $endpoint->location }}</dd>
                </div>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Resolved URL</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">
                        @if ($endpoint->resolved_url)
                            <a class="underline hover:text-slate-700" href="{{ $endpoint->resolved_url }}" target="_blank" rel="noopener noreferrer">{{ $endpoint->resolved_url }}</a>
                        @else
                            --
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Last Status Code</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->last_status_code ? $endpoint->last_status_code.' '.$statusText : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Last Checked At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($endpoint->last_checked_at) }}</dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Canonical URL Coverage</h2>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($canonicalStatus) }}">{{ $statusLabel($canonicalStatus) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Variant</th>
                            <th class="px-5 py-3 font-semibold">Final URL</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Redirects</th>
                            <th class="px-5 py-3 font-semibold">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($variants as $index => $variant)
                            @php
                                $chain = is_array($variant['redirect_chain'] ?? null) ? $variant['redirect_chain'] : [];
                                $redirectCount = (int) ($variant['redirect_count'] ?? 0);
                            @endphp
                            <tr>
                                <td class="px-5 py-4 font-mono text-slate-900 break-all">{{ $variant['url'] ?? '--' }}</td>
                                <td class="px-5 py-4 font-mono text-slate-900 break-all">{{ $variant['final_url'] ?? '--' }}</td>
                                <td class="px-5 py-4 font-mono text-slate-900">{{ $variant['status_code'] ?? '--' }}</td>
                                <td class="px-5 py-4 font-mono text-slate-900">
                                    @if ($redirectCount > 0 && count($chain) > 0)
                                        <button
                                            class="inline-flex items-center gap-1 text-slate-900"
                                            type="button"
                                            x-on:click="$refs['variantChain{{ $index }}'].toggleAttribute('hidden')"
                                            aria-label="Toggle redirect chain for {{ $variant['url'] ?? 'variant' }}"
                                        >
                                            {{ $redirectCount }} <span aria-hidden="true">▾</span>
                                        </button>
                                    @else
                                        {{ $redirectCount }}
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($variant['result'] ?? null) }}">{{ $resultLabel($variant['result'] ?? null) }}</span>
                                </td>
                            </tr>
                            @if ($redirectCount > 0 && count($chain) > 0)
                                <tr x-ref="variantChain{{ $index }}" hidden>
                                    <td class="bg-slate-50 px-8 pb-5 pt-0" colspan="5">
                                        <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
                                            @foreach ($chain as $hopIndex => $hop)
                                                @php($hopStatus = $hop['status_code'] ?? null)
                                                <div class="grid grid-cols-1 gap-2 border-t border-slate-200 px-4 py-3 first:border-t-0 md:grid-cols-[60px_140px_1fr]">
                                                    <div class="font-mono text-sm text-slate-500">#{{ $hopIndex + 1 }}</div>
                                                    <div class="font-mono text-sm text-slate-900">{{ $hopStatus ?: '--' }} {{ $hopStatus ? \Symfony\Component\HttpFoundation\Response::$statusTexts[$hopStatus] ?? '' : '' }}</div>
                                                    <div class="font-mono text-sm text-slate-900 break-all">
                                                        {{ $hop['url'] ?? '--' }}
                                                        @if (!empty($hop['location']))
                                                            <span class="px-1 text-slate-500">-></span>{{ $hop['location'] }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td class="px-5 py-6 text-center text-slate-500" colspan="5">Canonical coverage will be available after this domain endpoint is rechecked.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">DNS Resolution</h2>
                </div>
                <dl>
                    <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[180px_1fr]">
                        <dt class="px-5 py-3 text-sm font-medium text-slate-600">A Records</dt>
                        <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ !empty($dnsSummary['a_records']) ? implode(', ', $dnsSummary['a_records']) : '--' }}</dd>
                    </div>
                    <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[180px_1fr]">
                        <dt class="px-5 py-3 text-sm font-medium text-slate-600">AAAA Records</dt>
                        <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ !empty($dnsSummary['aaaa_records']) ? implode(', ', $dnsSummary['aaaa_records']) : '--' }}</dd>
                    </div>
                    <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[180px_1fr]">
                        <dt class="px-5 py-3 text-sm font-medium text-slate-600">CNAME</dt>
                        <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $dnsSummary['cname'] ?? '--' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Server Header Hints</h2>
                </div>
                <dl>
                    @forelse ($platformHeaders as $header => $value)
                        <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[180px_1fr]">
                            <dt class="px-5 py-3 text-sm font-medium text-slate-600">{{ $header }}</dt>
                            <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $value }}</dd>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-sm text-slate-500">No server header hints captured.</div>
                    @endforelse
                    <div class="grid grid-cols-1 border-t border-slate-200 sm:grid-cols-[180px_1fr]">
                        <dt class="px-5 py-3 text-sm font-medium text-slate-600">content-type</dt>
                        <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $endpoint->content_type ?: '--' }}</dd>
                    </div>
                    <div class="grid grid-cols-1 border-t border-slate-200 sm:grid-cols-[180px_1fr]">
                        <dt class="px-5 py-3 text-sm font-medium text-slate-600">response time</dt>
                        <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->response_time_ms !== null ? $endpoint->response_time_ms.' ms' : '--' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Coverage Notes</h2>
            </div>
            @if (count($variants) > 0)
                <ul class="divide-y divide-slate-200">
                    @foreach ($variants as $variant)
                        @if (!empty($variant['issue']))
                            <li class="flex gap-3 px-5 py-3 text-sm">
                                <span class="inline-flex h-6 rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($variant['result'] ?? null) }}">{{ $resultLabel($variant['result'] ?? null) }}</span>
                                <span><span class="font-mono">{{ $variant['url'] ?? '--' }}</span>: {{ $variant['issue'] }}</span>
                            </li>
                        @endif
                    @endforeach
                    @if (!collect($variants)->contains(fn ($variant) => !empty($variant['issue'])))
                        <li class="px-5 py-3 text-sm text-slate-600">No canonical coverage issues captured.</li>
                    @endif
                </ul>
            @else
                <div class="px-5 py-6 text-sm text-slate-500">Coverage notes will be available after this domain endpoint is rechecked.</div>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Details</h2>
            </div>
            <dl class="grid grid-cols-1 lg:grid-cols-2">
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Internal ID</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->id }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Failure Reason</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $endpoint->failure_reason ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Failure Category</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->failure_category ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Final Host</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $endpoint->resolved_host ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Final Scheme</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->resolved_scheme ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Host Changed</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $boolLabel($endpoint->host_changed) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Base Host Changed</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $boolLabel($endpoint->base_host_changed) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Created At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($endpoint->created_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Updated At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($endpoint->updated_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 lg:col-span-2 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Security Headers</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900">
                        @if (count($securityHeaders) > 0)
                            <ul class="space-y-1">
                                @foreach ($securityHeaders as $header => $detail)
                                    @php($present = is_array($detail) ? ($detail['present'] ?? false) : (bool) $detail)
                                    <li class="font-mono break-all">{{ $header }}: {{ $present ? 'present' : 'missing' }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="font-mono">--</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

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

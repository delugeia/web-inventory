@extends('layouts.app')

@section('content_max_width', 'max-w-screen-2xl')

@push('head')
    @vite(['resources/js/cached-source.js'])
    <style>
        pre.source-code {
            margin: 0;
            border-radius: 0;
            max-height: 32rem;
            font-size: 13px;
            line-height: 1.6;
        }

        pre.source-code code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        pre.source-code.source-wrap,
        pre.source-code.source-wrap code {
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
@endpush

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
        $latestPageAnalysis = $endpoint->latestPageAnalysis;
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

    <div x-data="{ confirmDelete: false, wrapPreparedInput: false }" class="space-y-6">
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
                <form method="POST" action="{{ route('endpoints.rendered-capture.store', $endpoint) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">Capture Rendered</button>
                </form>
                <form method="POST" action="{{ route('endpoints.page-analysis.store', $endpoint) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">Analyze Page</button>
                </form>
                <button class="inline-flex items-center justify-center rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" type="button" @click="confirmDelete = true">Delete</button>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Endpoint Profile</h2>
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
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Managed By</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900 break-all">{{ $endpoint->managed_by ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">DNS Provider</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900 break-all">{{ $endpoint->dns_provider ?: '--' }}</dd>
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
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Cached Copy</dt>
                    <dd class="flex flex-wrap gap-2 px-5 py-3 text-sm text-slate-900">
                        @if ($cachedContentAvailable)
                            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" href="{{ route('endpoints.cached', $endpoint) }}" target="_blank" rel="noopener noreferrer">View</a>
                            <a class="inline-flex items-center justify-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" href="{{ route('endpoints.cached.source', $endpoint) }}" target="_blank" rel="noopener noreferrer">View Source</a>
                        @else
                            <span class="font-mono">--</span>
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Page Title</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900 break-all">{{ $endpoint->page_title ?: '--' }}</dd>
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
                    @forelse ($platformHeaders as $headerName => $value)
                        <div class="grid grid-cols-1 border-t border-slate-200 first:border-t-0 sm:grid-cols-[180px_1fr]">
                            <dt class="px-5 py-3 text-sm font-medium text-slate-600">{{ $headerName }}</dt>
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
                <h2 class="text-lg font-semibold text-slate-900">Miscellaneous Details</h2>
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
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Redirect Followed</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $boolLabel($endpoint->redirect_followed) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Redirect Count</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->redirect_count ?? '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">HTTP To HTTPS Redirect</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $boolLabel($endpoint->http_to_https_redirect) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Cached Content Length</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">
                        {{ is_string($endpoint->page_content) ? mb_strlen($endpoint->page_content, 'UTF-8').' chars' : '--' }}
                    </dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Capture Status</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->rendered_capture_status ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Page Title</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900 break-all">{{ $endpoint->rendered_page_title ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Text Length</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ is_string($endpoint->rendered_page_text) ? mb_strlen($endpoint->rendered_page_text, 'UTF-8').' chars' : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered HTML Length</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ is_string($endpoint->rendered_page_content) ? mb_strlen($endpoint->rendered_page_content, 'UTF-8').' chars' : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Capture Time</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $endpoint->rendered_capture_duration_ms !== null ? $endpoint->rendered_capture_duration_ms.' ms' : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Captured At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($endpoint->rendered_captured_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 lg:col-span-2 sm:grid-cols-[180px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Rendered Capture Error</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $endpoint->rendered_capture_error ?: '--' }}</dd>
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
                                @foreach ($securityHeaders as $headerName => $detail)
                                    @php($present = is_array($detail) ? ($detail['present'] ?? false) : (bool) $detail)
                                    <li class="font-mono break-all">{{ $headerName }}: {{ $present ? 'present' : 'missing' }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="font-mono">--</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">AI Details</h2>
            </div>
            <dl class="grid grid-cols-1 lg:grid-cols-2">
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Prepared Input Status</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $pageAnalysisInput->shouldAnalyze ? 'ready' : 'skipped' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Prepared Skip Reason</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $pageAnalysisInput->skipReason ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Prepared Input Hash</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $pageAnalysisInput->inputHash ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Prepared Character Count</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $pageAnalysisInput->inputCharacterCount ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Analysis Status</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $latestPageAnalysis?->status ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Page Status</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->page_status ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Confidence</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $latestPageAnalysis?->confidence !== null ? $latestPageAnalysis->confidence : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Model</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->model ?: config('services.openai.model', '--') }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Directive Version</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->directive_version ?: ($pageAnalysisInput->payload['directive_version'] ?? '--') }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Recorded Input Hash</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->input_hash ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Recorded Character Count</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $latestPageAnalysis?->input_character_count !== null ? $latestPageAnalysis->input_character_count : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Analysis Response Time</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ $latestPageAnalysis?->response_time_ms !== null ? $latestPageAnalysis->response_time_ms.' ms' : '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Failure Category</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->failure_category ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Error Message</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">{{ $latestPageAnalysis?->error_message ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 lg:col-span-2 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Summary</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900">{{ $latestPageAnalysis?->summary ?: '--' }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 lg:col-span-2 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Reasons</dt>
                    <dd class="px-5 py-3 text-sm text-slate-900">
                        @if (is_array($latestPageAnalysis?->reasons) && count($latestPageAnalysis->reasons) > 0)
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($latestPageAnalysis->reasons as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="font-mono">--</span>
                        @endif
                    </dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 lg:col-span-2 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Signals</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900 break-all">
                        {{ is_array($latestPageAnalysis?->signals) && count($latestPageAnalysis->signals) > 0 ? json_encode($latestPageAnalysis->signals, JSON_UNESCAPED_SLASHES) : '--' }}
                    </dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Queued At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($latestPageAnalysis?->queued_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Started At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($latestPageAnalysis?->started_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Completed At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($latestPageAnalysis?->completed_at) }}</dd>
                </div>
                <div class="grid grid-cols-1 border-b border-slate-200 sm:grid-cols-[220px_1fr]">
                    <dt class="px-5 py-3 text-sm font-medium text-slate-600">Failed At</dt>
                    <dd class="px-5 py-3 font-mono text-sm text-slate-900">{{ \App\Support\DateTimeDisplay::format($latestPageAnalysis?->failed_at) }}</dd>
                </div>
            </dl>

            <details class="border-t border-slate-200">
                <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-900 hover:bg-slate-50">Prepared Input JSON</summary>
                <div class="flex items-center justify-between gap-4 border-t border-slate-200 bg-slate-50 px-5 py-3">
                    <p class="text-sm text-slate-600">Generated from the current endpoint snapshot.</p>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input class="rounded border-slate-300 text-slate-900 shadow-sm focus:border-slate-500 focus:ring-slate-500" type="checkbox" x-model="wrapPreparedInput">
                        <span>Line wrap</span>
                    </label>
                </div>
                <div class="border-t border-slate-200">
                    @if ($pageAnalysisInput->inputText !== '')
                        <pre class="source-code line-numbers language-json" x-bind:class="{ 'source-wrap': wrapPreparedInput }"><code class="language-json">{{ $pageAnalysisInput->inputText }}</code></pre>
                    @else
                        <p class="px-5 py-6 text-sm text-slate-600">Prepared input is unavailable because this endpoint is skipped: {{ $pageAnalysisInput->skipReason ?: 'unknown' }}.</p>
                    @endif
                </div>
            </details>
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

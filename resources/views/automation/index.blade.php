@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        @if ($activeRun)
            data-run-status-url="{{ route('automation.runs.show', $activeRun) }}"
        @endif
    >
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Automation</h1>
            <p class="mt-1 text-sm text-slate-600">Run repeatable inventory maintenance tasks.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Resolve next endpoint</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Checks the next unresolved endpoint, prioritizing never-checked locations alphabetically and then the oldest checked location.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <form method="POST" action="{{ route('endpoints.resolve.next.store') }}">
                        @csrf
                        <button class="inline-flex w-full items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto" type="submit">Resolve Next</button>
                    </form>

                    <form class="flex flex-col gap-2 sm:flex-row sm:items-end" method="POST" action="{{ route('automation.resolve-multiple.store') }}">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="endpoint_count">Endpoints</label>
                            <select class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 sm:w-32" id="endpoint_count" name="endpoint_count">
                                @foreach ([2, 3, 4, 5, 10, 25, 50] as $count)
                                    <option value="{{ $count }}" @selected(old('endpoint_count', 4) == $count)>{{ $count }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="inline-flex w-full items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto" type="submit">Start Batch</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Endpoint queue</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        @if ($activeRun)
                            Showing live results for resolution run #{{ $activeRun->id }}.
                        @else
                            Ordered by the next endpoints that will be resolved first.
                        @endif
                    </p>
                </div>
                @if ($activeRun)
                    @php
                        $presentationItems = $activeRun->items->map(function ($item) {
                            $presentationStatus = $item->status === 'failed' && filled($item->failure_reason)
                                ? 'unresolved'
                                : $item->status;

                            return [
                                'status' => $presentationStatus,
                            ];
                        });
                        $summaryParts = [
                            $presentationItems->where('status', 'resolved')->count().' resolved',
                        ];
                        $unresolvedCount = $presentationItems->where('status', 'unresolved')->count();
                        $failedCount = $presentationItems->where('status', 'failed')->count();

                        if ($unresolvedCount > 0) {
                            $summaryParts[] = $unresolvedCount.' unresolved';
                        }

                        if ($failedCount > 0) {
                            $summaryParts[] = $failedCount.' failed';
                        }

                        $summaryParts[] = $activeRun->total_count.' total';
                    @endphp
                    <p class="text-sm font-medium text-slate-700" data-run-summary>
                        {{ ucfirst($activeRun->status) }}: {{ implode(', ', $summaryParts) }}
                    </p>
                @endif
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Location</th>
                            <th class="px-4 py-3 font-medium">Resolved URL</th>
                            <th class="px-4 py-3 font-medium">Last Status Code</th>
                            <th class="px-4 py-3 font-medium">Last Checked At</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @if ($activeRun)
                            @forelse ($activeRun->items as $item)
                                @php
                                    $presentationStatus = $item->status === 'failed' && filled($item->failure_reason)
                                        ? 'unresolved'
                                        : $item->status;
                                    $statusClasses = match ($presentationStatus) {
                                        'running' => 'bg-amber-100 text-amber-800',
                                        'resolved' => 'bg-emerald-100 text-emerald-800',
                                        'unresolved' => 'bg-amber-100 text-amber-800',
                                        'failed' => 'bg-rose-100 text-rose-800',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                    $statusLabel = $presentationStatus === 'unresolved'
                                        ? ($item->failure_reason ?: 'Unresolved')
                                        : ucfirst($presentationStatus);
                                @endphp
                                <tr
                                    class="transition {{ $presentationStatus === 'resolved' ? 'bg-emerald-50' : ($presentationStatus === 'unresolved' ? 'bg-amber-50' : ($presentationStatus === 'failed' ? 'bg-rose-50' : '')) }}"
                                    data-endpoint-row
                                    data-run-item-id="{{ $item->id }}"
                                >
                                    <td class="px-4 py-3 font-mono text-slate-500">#{{ $item->position }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-900 break-all" data-location>{{ $item->location }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-700 break-all" data-resolved-url>{{ $item->resolved_url ?: '--' }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-700" data-last-status-code>{{ $item->last_status_code ?: '--' }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-700" data-last-checked-at>
                                        {{ $item->last_checked_at ? $item->last_checked_at->toDayDateTimeString() : '--' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusClasses }}"
                                            data-endpoint-status
                                        >
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-slate-500" colspan="6">No endpoints available.</td>
                                </tr>
                            @endforelse
                        @else
                        @forelse ($endpoints as $endpoint)
                            <tr
                                class="transition"
                                data-endpoint-row
                                data-row-index="{{ $loop->index }}"
                            >
                                <td class="px-4 py-3 font-mono text-slate-500">#{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-mono text-slate-900 break-all">{{ $endpoint->location }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">--</td>
                                <td class="px-4 py-3 font-mono text-slate-700">--</td>
                                <td class="px-4 py-3 font-mono text-slate-700" data-last-checked-at>
                                    {{ $endpoint->last_checked_at ? $endpoint->last_checked_at->toDayDateTimeString() : '--' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"
                                        data-endpoint-status
                                    >
                                        Waiting
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-6 text-center text-slate-500" colspan="6">No endpoints available.</td>
                            </tr>
                        @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.querySelector('[data-run-status-url]');
            if (!container) {
                return;
            }

            const statusClasses = {
                queued: 'inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700',
                running: 'inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800',
                resolved: 'inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800',
                unresolved: 'inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800',
                failed: 'inline-flex rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-800'
            };

            const applyRun = (run) => {
                const summary = document.querySelector('[data-run-summary]');
                if (summary) {
                    const summaryParts = [`${run.resolved_count} resolved`];

                    if (run.unresolved_count > 0) {
                        summaryParts.push(`${run.unresolved_count} unresolved`);
                    }

                    if (run.failed_count > 0) {
                        summaryParts.push(`${run.failed_count} failed`);
                    }

                    summaryParts.push(`${run.total_count} total`);
                    summary.textContent = `${run.status.charAt(0).toUpperCase()}${run.status.slice(1)}: ${summaryParts.join(', ')}`;
                }

                run.items.forEach((item) => {
                    const row = document.querySelector(`[data-run-item-id="${item.id}"]`);
                    if (!row) {
                        return;
                    }

                    const status = row.querySelector('[data-endpoint-status]');
                    const resolvedUrl = row.querySelector('[data-resolved-url]');
                    const lastStatusCode = row.querySelector('[data-last-status-code]');
                    const lastCheckedAt = row.querySelector('[data-last-checked-at]');

                    const presentationStatus = item.presentation_status || item.status;

                    row.classList.toggle('bg-emerald-50', presentationStatus === 'resolved');
                    row.classList.toggle('bg-amber-50', presentationStatus === 'unresolved');
                    row.classList.toggle('bg-rose-50', presentationStatus === 'failed');

                    if (status) {
                        status.textContent = item.status_label || (
                            presentationStatus === 'unresolved'
                                ? (item.failure_reason || 'Unresolved')
                                : `${presentationStatus.charAt(0).toUpperCase()}${presentationStatus.slice(1)}`
                        );
                        status.className = statusClasses[presentationStatus] || statusClasses.queued;
                    }

                    if (resolvedUrl) {
                        resolvedUrl.textContent = item.resolved_url || '--';
                    }

                    if (lastStatusCode) {
                        lastStatusCode.textContent = item.last_status_code || '--';
                    }

                    if (lastCheckedAt) {
                        lastCheckedAt.textContent = item.last_checked_at_display || '--';
                    }
                });
            };

            const poll = () => {
                fetch(container.dataset.runStatusUrl, {
                    headers: {
                        Accept: 'application/json'
                    }
                })
                    .then((response) => response.json())
                    .then((run) => {
                        applyRun(run);

                        if (!['completed', 'failed'].includes(run.status)) {
                            window.setTimeout(poll, 2000);
                        }
                    });
            };

            poll();
        });
    </script>
@endsection

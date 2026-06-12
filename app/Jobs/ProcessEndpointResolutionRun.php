<?php

namespace App\Jobs;

use App\Models\EndpointResolutionRun;
use App\Models\EndpointResolutionRunItem;
use App\Services\EndpointResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEndpointResolutionRun implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $runId
    ) {
    }

    public function handle(EndpointResolver $resolver): void
    {
        $run = EndpointResolutionRun::query()->find($this->runId);

        if ($run === null || in_array($run->status, ['completed', 'failed'], true)) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
        ]);

        try {
            $run->items()
                ->whereIn('status', ['queued', 'running'])
                ->orderBy('position')
                ->each(function (EndpointResolutionRunItem $item) use ($resolver, $run): void {
                    $item->update(['status' => 'running']);

                    $endpoint = $item->endpoint;
                    if ($endpoint === null) {
                        $item->update([
                            'status' => 'failed',
                            'failure_reason' => 'endpoint_missing',
                            'last_checked_at' => now(),
                        ]);
                        $this->refreshRunCounts($run);

                        return;
                    }

                    $result = $resolver->resolve($endpoint);
                    $endpoint->refresh();

                    $item->update([
                        'status' => $result['resolved'] ? 'resolved' : 'unresolved',
                        'resolved_url' => $endpoint->resolved_url,
                        'resolved_host' => $endpoint->resolved_host,
                        'resolved_scheme' => $endpoint->resolved_scheme,
                        'host_changed' => $endpoint->host_changed,
                        'base_host_changed' => $endpoint->base_host_changed,
                        'http_to_https_redirect' => $endpoint->http_to_https_redirect,
                        'content_type' => $endpoint->content_type,
                        'response_time_ms' => $endpoint->response_time_ms,
                        'dns_summary' => $endpoint->dns_summary,
                        'platform_headers' => $endpoint->platform_headers,
                        'security_headers' => $endpoint->security_headers,
                        'canonical_url_check' => $endpoint->canonical_url_check,
                        'last_status_code' => $endpoint->last_status_code,
                        'failure_reason' => $endpoint->failure_reason,
                        'failure_category' => $endpoint->failure_category,
                        'last_checked_at' => $endpoint->last_checked_at,
                    ]);

                    $this->refreshRunCounts($run);
                });

            $this->refreshRunCounts($run, 'completed');
        } catch (\Throwable $e) {
            $this->refreshRunCounts($run, 'failed');

            throw $e;
        }
    }

    private function refreshRunCounts(EndpointResolutionRun $run, ?string $status = null): void
    {
        $counts = EndpointResolutionRunItem::query()
            ->where('endpoint_resolution_run_id', $run->id)
            ->selectRaw("sum(case when status = 'resolved' then 1 else 0 end) as resolved_count")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed_count")
            ->first();

        $attributes = [
            'resolved_count' => (int) ($counts?->resolved_count ?? 0),
            'failed_count' => (int) ($counts?->failed_count ?? 0),
        ];

        if ($status !== null) {
            $attributes['status'] = $status;
            $attributes['finished_at'] = now();
        }

        $run->update($attributes);
        $run->refresh();
    }
}

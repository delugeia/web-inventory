<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEndpointResolutionRun;
use App\Models\Endpoint;
use App\Models\EndpointResolutionRun;
use App\Support\DateTimeDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $activeRun = null;
        $runId = $request->integer('run');

        if ($runId > 0) {
            $activeRun = EndpointResolutionRun::query()
                ->with('items')
                ->find($runId);
        }

        return view('automation.index', [
            'activeRun' => $activeRun,
            'endpoints' => Endpoint::query()
                ->nextToResolve()
                ->get(['id', 'location', 'last_checked_at']),
        ]);
    }

    public function resolveMultipleStore(Request $request)
    {
        $validated = $request->validate([
            'endpoint_count' => ['required', 'integer', 'in:1,2,3,4,5,10,25,50'],
        ]);

        $endpoints = Endpoint::query()
            ->nextToResolve()
            ->limit($validated['endpoint_count'])
            ->get(['id', 'location']);

        if ($endpoints->isEmpty()) {
            return redirect()
                ->route('automation.index')
                ->with('status', 'There are no endpoints to resolve.');
        }

        $run = DB::transaction(function () use ($validated, $endpoints): EndpointResolutionRun {
            $run = EndpointResolutionRun::query()->create([
                'requested_count' => $validated['endpoint_count'],
                'total_count' => $endpoints->count(),
                'status' => 'pending',
            ]);

            foreach ($endpoints as $index => $endpoint) {
                $run->items()->create([
                    'endpoint_id' => $endpoint->id,
                    'position' => $index + 1,
                    'location' => $endpoint->location,
                    'status' => 'queued',
                ]);
            }

            return $run;
        });

        ProcessEndpointResolutionRun::dispatch($run->id);

        return redirect()
            ->route('automation.index', ['run' => $run])
            ->with('status', "Started resolving {$run->total_count} endpoints.");
    }

    public function runStatus(EndpointResolutionRun $run)
    {
        $run->load('items');
        $items = $run->items->map(function ($item) {
            $presentationStatus = $this->presentationStatus($item->status, $item->failure_reason);

            return [
                'id' => $item->id,
                'position' => $item->position,
                'location' => $item->location,
                'status' => $item->status,
                'presentation_status' => $presentationStatus,
                'status_label' => $presentationStatus === 'unresolved'
                    ? ($item->failure_reason ?: 'Unresolved')
                    : ucfirst($presentationStatus),
                'resolved_url' => $item->resolved_url,
                'resolved_host' => $item->resolved_host,
                'resolved_scheme' => $item->resolved_scheme,
                'host_changed' => $item->host_changed,
                'base_host_changed' => $item->base_host_changed,
                'http_to_https_redirect' => $item->http_to_https_redirect,
                'content_type' => $item->content_type,
                'response_time_ms' => $item->response_time_ms,
                'dns_summary' => $item->dns_summary,
                'platform_headers' => $item->platform_headers,
                'security_headers' => $item->security_headers,
                'canonical_url_check' => $item->canonical_url_check,
                'last_status_code' => $item->last_status_code,
                'failure_reason' => $item->failure_reason,
                'failure_category' => $item->failure_category,
                'last_checked_at' => $item->last_checked_at?->toIso8601String(),
                'last_checked_at_display' => DateTimeDisplay::format($item->last_checked_at),
            ];
        });

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'requested_count' => $run->requested_count,
            'total_count' => $run->total_count,
            'resolved_count' => $items->where('presentation_status', 'resolved')->count(),
            'unresolved_count' => $items->where('presentation_status', 'unresolved')->count(),
            'failed_count' => $items->where('presentation_status', 'failed')->count(),
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'items' => $items->values(),
        ]);
    }

    private function presentationStatus(string $status, ?string $failureReason): string
    {
        if ($status === 'failed' && $failureReason !== null && $failureReason !== '') {
            return 'unresolved';
        }

        return $status;
    }
}

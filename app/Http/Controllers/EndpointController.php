<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEndpointRequest;
use App\Http\Requests\UpdateEndpointRequest;
use App\Jobs\AnalyzeEndpointPage;
use App\Jobs\CaptureEndpointRenderedPage;
use App\Models\Endpoint;
use App\Models\EndpointPageAnalysis;
use App\Services\EndpointResolver;
use App\Services\PageAnalysisInputBuilder;
use App\Support\EndpointLocationNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

class EndpointController extends Controller
{
    public function index()
    {
        $perPageOptions = [15, 25, 50, 100];
        $perPage = (int) request('per_page', 15);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 15;
        }

        $allowedSorts = [
            'location',
            'managed_by',
            'dns_provider',
            'resolved_url',
            'last_status_code',
            'last_checked_at',
        ];

        $sort = request('sort', 'location');
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'location';
        }

        $direction = request('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) request('q', ''));

        $endpoints = Endpoint::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('location', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('managed_by', 'like', "%{$search}%")
                        ->orWhere('dns_provider', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->appends([
                'q' => $search !== '' ? $search : null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ]);

        return view('endpoints.index', compact('endpoints', 'search', 'sort', 'direction', 'perPage', 'perPageOptions'));
    }

    public function show(Endpoint $endpoint, PageAnalysisInputBuilder $pageAnalysisInputBuilder)
    {
        $endpoint->load('latestPageAnalysis');
        $pageAnalysisInput = $pageAnalysisInputBuilder->build($endpoint);
        $cachedContentAvailable = $this->cachedContentFor($endpoint) !== null;

        return view('endpoints.show', compact('endpoint', 'pageAnalysisInput', 'cachedContentAvailable'));
    }

    public function cached(Endpoint $endpoint)
    {
        $this->abortIfMissingCachedContent($endpoint);
        $cachedContentSource = $this->cachedContentSourceFor($endpoint);

        return response()
            ->view('endpoints.cached', compact('endpoint', 'cachedContentSource'))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function cachedContent(Endpoint $endpoint)
    {
        $this->abortIfMissingCachedContent($endpoint);

        return response($this->cachedContentFor($endpoint), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function cachedSource(Endpoint $endpoint)
    {
        $this->abortIfMissingCachedContent($endpoint);
        $cachedContent = $this->cachedContentFor($endpoint);
        $cachedContentSource = $this->cachedContentSourceFor($endpoint);

        return response()
            ->view('endpoints.cached-source', compact('endpoint', 'cachedContent', 'cachedContentSource'))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function resolveStore(Endpoint $endpoint, EndpointResolver $resolver)
    {
        $result = $resolver->resolve($endpoint);

        if ($result['resolved']) {
            return redirect()
                ->route('endpoints.show', $endpoint)
                ->with('status', "Rechecked {$endpoint->location}: resolved to {$result['resolved_url']} with status {$result['status_code']}.");
        }

        return redirect()
            ->route('endpoints.show', $endpoint)
            ->with('status', "Recheck failed for {$endpoint->location}: {$result['failure_reason']}");
    }

    public function renderedCaptureStore(Endpoint $endpoint)
    {
        if (! (bool) config('rendered_capture.enabled', false)) {
            return redirect()
                ->route('endpoints.show', $endpoint)
                ->with('status', 'Rendered capture is disabled. Enable RENDERED_CAPTURE_ENABLED to use it.');
        }

        CaptureEndpointRenderedPage::dispatch($endpoint->id);

        $endpoint->update([
            'rendered_capture_status' => 'queued',
            'rendered_capture_error' => null,
        ]);

        return redirect()
            ->route('endpoints.show', $endpoint)
            ->with('status', "Queued rendered capture for {$endpoint->location}.");
    }

    public function pageAnalysisStore(Endpoint $endpoint, PageAnalysisInputBuilder $pageAnalysisInputBuilder)
    {
        if (! (bool) config('services.openai.analysis_enabled', false)) {
            return redirect()
                ->route('endpoints.show', $endpoint)
                ->with('status', 'AI page analysis is disabled. Enable OPENAI_ANALYSIS_ENABLED to use it.');
        }

        $pageAnalysisInput = $pageAnalysisInputBuilder->build($endpoint);

        if (! $pageAnalysisInput->shouldAnalyze) {
            return redirect()
                ->route('endpoints.show', $endpoint)
                ->with('status', "Skipped AI page analysis for {$endpoint->location}: {$pageAnalysisInput->skipReason}.");
        }

        $existing = EndpointPageAnalysis::query()
            ->where('endpoint_id', $endpoint->id)
            ->where('input_hash', $pageAnalysisInput->inputHash)
            ->where('directive_version', $pageAnalysisInput->payload['directive_version'] ?? null)
            ->where('status', 'completed')
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('endpoints.show', $endpoint)
                ->with('status', "AI page analysis already exists for {$endpoint->location}.");
        }

        $analysis = EndpointPageAnalysis::query()->firstOrNew([
            'endpoint_id' => $endpoint->id,
            'input_hash' => $pageAnalysisInput->inputHash,
            'directive_version' => $pageAnalysisInput->payload['directive_version'] ?? null,
        ]);

        $analysis->fill([
            'status' => 'queued',
            'page_status' => null,
            'summary' => null,
            'confidence' => null,
            'reasons' => null,
            'signals' => null,
            'model' => config('services.openai.model'),
            'input_character_count' => $pageAnalysisInput->inputCharacterCount,
            'response_time_ms' => null,
            'failure_category' => null,
            'error_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ]);
        $analysis->save();

        AnalyzeEndpointPage::dispatch($analysis->id);

        return redirect()
            ->route('endpoints.show', $endpoint)
            ->with('status', "Queued AI page analysis for {$endpoint->location}.");
    }

    private function abortIfMissingCachedContent(Endpoint $endpoint): void
    {
        if ($this->cachedContentFor($endpoint) === null) {
            abort(404);
        }
    }

    private function cachedContentFor(Endpoint $endpoint): ?string
    {
        if ($this->cachedContentSourceFor($endpoint) === 'rendered') {
            return $endpoint->rendered_page_content;
        }

        if (is_string($endpoint->page_content) && trim($endpoint->page_content) !== '') {
            return $endpoint->page_content;
        }

        return null;
    }

    private function cachedContentSourceFor(Endpoint $endpoint): ?string
    {
        if (is_string($endpoint->rendered_page_content) && trim($endpoint->rendered_page_content) !== '') {
            return 'rendered';
        }

        if (is_string($endpoint->page_content) && trim($endpoint->page_content) !== '') {
            return 'cached';
        }

        return null;
    }

    public function create()
    {
        return view('endpoints.create');
    }

    public function store(StoreEndpointRequest $request)
    {
        $endpoint = Endpoint::create($request->validated());

        return redirect()->route('endpoints.show', $endpoint);
    }

    public function edit(Endpoint $endpoint)
    {
        return view('endpoints.edit', compact('endpoint'));
    }

    public function update(UpdateEndpointRequest $request, Endpoint $endpoint)
    {
        $endpoint->update($request->validated());

        return redirect()->route('endpoints.show', $endpoint);
    }

    public function destroy(Endpoint $endpoint)
    {
        $endpoint->delete();

        return redirect()->route('endpoints.index');
    }

    public function importForm()
    {
        return view('endpoints.import');
    }

    public function importStore()
    {
        $lines = (string) request('lines', '');
        $rawLines = preg_split('/\r\n|\r|\n/', $lines);
        $errors = new MessageBag;
        $rows = [];
        $now = now();

        foreach ($rawLines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $location = $parts[0] ?? '';
            $location = EndpointLocationNormalizer::normalize($location);
            $name = isset($parts[1]) ? trim($parts[1]) : null;
            $lineNumber = $index + 1;

            if ($location === '') {
                $errors->add('lines', "Line {$lineNumber}: location is required.");

                continue;
            }

            if (preg_match('/\s/', $location)) {
                $errors->add('lines', "Line {$lineNumber}: location cannot contain spaces.");

                continue;
            }

            if (mb_strlen($location) > 2048) {
                $errors->add('lines', "Line {$lineNumber}: location must be 2048 characters or fewer.");

                continue;
            }

            if ($name !== null && mb_strlen($name) > 255) {
                $errors->add('lines', "Line {$lineNumber}: name must be 255 characters or fewer.");

                continue;
            }

            [$managedBy, $dnsProvider] = $this->splitEndpointManagementName($name);

            $rows[] = [
                'location' => $location,
                'name' => null,
                'managed_by' => $managedBy,
                'dns_provider' => $dnsProvider,
                'resolved_url' => null,
                'last_status_code' => null,
                'last_checked_at' => null,
                'failure_reason' => null,
                'redirect_followed' => false,
                'redirect_count' => 0,
                'redirect_chain' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($errors->any()) {
            return back()->withErrors($errors)->withInput();
        }

        if (count($rows) === 0) {
            return back()->withErrors(['lines' => 'Please provide at least one valid line.'])->withInput();
        }

        DB::transaction(function () use ($rows) {
            Endpoint::query()->insert($rows);
        });

        return redirect()->route('endpoints.index')->with('status', 'Imported '.count($rows).' endpoints.');
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitEndpointManagementName(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+-\s+/', trim($name), 2);

        if (! is_array($parts) || count($parts) !== 2) {
            return [trim($name), null];
        }

        $managedBy = trim($parts[0]);
        $dnsProvider = trim($parts[1]);

        return [
            $managedBy !== '' ? $managedBy : null,
            $dnsProvider !== '' ? $dnsProvider : null,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ProcessEndpointResolutionRun;
use App\Models\Endpoint;
use App\Models\EndpointResolutionRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resolver.skip_dns_check', true);
        config()->set('resolver.connect_timeout', 1);
        config()->set('resolver.request_timeout', 1);
    }

    public function test_automation_page_shows_batch_resolve_action(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Automation')
            ->assertSee('Resolve endpoint batch')
            ->assertSee('Start Batch')
            ->assertDontSee('Resolve Next')
            ->assertDontSee('/endpoints/resolve/next', false)
            ->assertSee(route('automation.resolve-multiple.store'), false)
            ->assertSee('name="endpoint_count"', false)
            ->assertSee('<option value="1"', false)
            ->assertSee('Endpoint queue')
            ->assertSee('Last Checked At');
    }

    public function test_automation_page_shows_endpoint_queue_in_resolution_order(): void
    {
        $user = User::factory()->create();

        Endpoint::query()->create([
            'location' => 'checked.example',
            'last_checked_at' => CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC'),
        ]);
        Endpoint::query()->create([
            'location' => 'zeta.example',
        ]);
        Endpoint::query()->create([
            'location' => 'alpha.example',
        ]);

        $this
            ->actingAs($user)
            ->get(route('automation.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'alpha.example',
                'zeta.example',
                'checked.example',
            ])
            ->assertSee('Resolved URL')
            ->assertSee('Last Status Code')
            ->assertSee('Fri, 12 Jun 2026, 5:50 PM')
            ->assertSee('Waiting')
            ->assertSee('--');
    }

    public function test_resolve_multiple_creates_run_items_and_dispatches_job(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Endpoint::query()->create(['location' => 'zeta.example']);
        Endpoint::query()->create(['location' => 'alpha.example']);

        $response = $this
            ->actingAs($user)
            ->post(route('automation.resolve-multiple.store'), [
                'endpoint_count' => 4,
            ]);

        $run = EndpointResolutionRun::query()->firstOrFail();

        $response
            ->assertRedirect(route('automation.index', ['run' => $run]))
            ->assertSessionHas('status', 'Started resolving 2 endpoints.');

        $this->assertSame(4, $run->requested_count);
        $this->assertSame(2, $run->total_count);
        $this->assertSame('pending', $run->status);
        $this->assertSame(['alpha.example', 'zeta.example'], $run->items()->pluck('location')->all());

        Queue::assertPushed(ProcessEndpointResolutionRun::class, fn ($job) => $job->runId === $run->id);
    }

    public function test_resolve_multiple_accepts_one_endpoint_batch(): void
    {
        $user = User::factory()->create();
        Queue::fake();

        Endpoint::query()->create(['location' => 'zeta.example']);
        Endpoint::query()->create(['location' => 'alpha.example']);

        $response = $this
            ->actingAs($user)
            ->post(route('automation.resolve-multiple.store'), [
                'endpoint_count' => 1,
            ]);

        $run = EndpointResolutionRun::query()->firstOrFail();

        $response
            ->assertRedirect(route('automation.index', ['run' => $run]))
            ->assertSessionHas('status', 'Started resolving 1 endpoints.');

        $this->assertSame(1, $run->requested_count);
        $this->assertSame(1, $run->total_count);
        $this->assertSame(['alpha.example'], $run->items()->pluck('location')->all());

        Queue::assertPushed(ProcessEndpointResolutionRun::class, fn ($job) => $job->runId === $run->id);
    }

    public function test_run_status_returns_item_results(): void
    {
        $user = User::factory()->create();
        $run = EndpointResolutionRun::query()->create([
            'requested_count' => 2,
            'total_count' => 2,
            'resolved_count' => 1,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $item = $run->items()->create([
            'position' => 1,
            'location' => 'example.com',
            'status' => 'resolved',
            'resolved_url' => 'https://example.com/',
            'resolved_host' => 'example.com',
            'resolved_scheme' => 'https',
            'host_changed' => false,
            'base_host_changed' => false,
            'http_to_https_redirect' => false,
            'content_type' => 'text/html',
            'response_time_ms' => 12,
            'dns_summary' => [
                'a_count' => 1,
                'aaaa_count' => 0,
                'cname' => null,
            ],
            'platform_headers' => [
                'server' => 'nginx',
            ],
            'security_headers' => [
                'strict-transport-security' => [
                    'present' => true,
                    'value' => 'max-age=31536000',
                ],
            ],
            'last_status_code' => 200,
            'last_checked_at' => now(),
        ]);
        $unresolvedItem = $run->items()->create([
            'position' => 2,
            'location' => 'missing.example',
            'status' => 'unresolved',
            'failure_reason' => 'dns_not_found',
            'last_checked_at' => CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC'),
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('automation.runs.show', $run))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.status', 'resolved')
            ->assertJsonPath('items.0.resolved_url', 'https://example.com/')
            ->assertJsonPath('items.0.resolved_host', 'example.com')
            ->assertJsonPath('items.0.resolved_scheme', 'https')
            ->assertJsonPath('items.0.host_changed', false)
            ->assertJsonPath('items.0.base_host_changed', false)
            ->assertJsonPath('items.0.http_to_https_redirect', false)
            ->assertJsonPath('items.0.content_type', 'text/html')
            ->assertJsonPath('items.0.response_time_ms', 12)
            ->assertJsonPath('items.0.dns_summary.a_count', 1)
            ->assertJsonPath('items.0.platform_headers.server', 'nginx')
            ->assertJsonPath('items.0.security_headers.strict-transport-security.present', true)
            ->assertJsonPath('items.0.last_status_code', 200)
            ->assertJsonPath('items.1.id', $unresolvedItem->id)
            ->assertJsonPath('items.1.status', 'unresolved')
            ->assertJsonPath('items.1.presentation_status', 'unresolved')
            ->assertJsonPath('items.1.status_label', 'dns_not_found')
            ->assertJsonPath('items.1.failure_reason', 'dns_not_found')
            ->assertJsonPath('items.1.last_checked_at_display', 'Fri, 12 Jun 2026, 5:50 PM')
            ->assertJsonPath('started_at', $run->started_at?->toIso8601String())
            ->assertJsonPath('finished_at', $run->finished_at?->toIso8601String());
    }

    public function test_automation_page_presents_legacy_failed_resolver_outcomes_as_unresolved(): void
    {
        $user = User::factory()->create();
        $run = EndpointResolutionRun::query()->create([
            'requested_count' => 1,
            'total_count' => 1,
            'failed_count' => 1,
            'status' => 'completed',
        ]);
        $run->items()->create([
            'position' => 1,
            'location' => 'missing.example',
            'status' => 'failed',
            'failure_reason' => 'dns_not_found',
            'last_checked_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('automation.index', ['run' => $run]))
            ->assertOk()
            ->assertSee('dns_not_found')
            ->assertSee('Completed: 0 resolved, 1 unresolved, 1 total')
            ->assertDontSee('1 failed');

        $this
            ->actingAs($user)
            ->getJson(route('automation.runs.show', $run))
            ->assertOk()
            ->assertJsonPath('unresolved_count', 1)
            ->assertJsonPath('failed_count', 0)
            ->assertJsonPath('items.0.status', 'failed')
            ->assertJsonPath('items.0.presentation_status', 'unresolved')
            ->assertJsonPath('items.0.status_label', 'dns_not_found');
    }

    public function test_process_endpoint_resolution_run_updates_items_with_resolver_results(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);
        $secondEndpoint = Endpoint::query()->create([
            'location' => 'second.example',
        ]);
        $run = EndpointResolutionRun::query()->create([
            'requested_count' => 2,
            'total_count' => 2,
            'status' => 'pending',
        ]);
        $item = $run->items()->create([
            'endpoint_id' => $endpoint->id,
            'position' => 1,
            'location' => $endpoint->location,
            'status' => 'queued',
        ]);
        $secondItem = $run->items()->create([
            'endpoint_id' => $secondEndpoint->id,
            'position' => 2,
            'location' => $secondEndpoint->location,
            'status' => 'queued',
        ]);

        Http::fake([
            '*' => Http::response('ok', 200, [
                'Content-Type' => 'text/html',
                'Server' => 'nginx',
                'Strict-Transport-Security' => 'max-age=31536000',
            ]),
        ]);

        app(ProcessEndpointResolutionRun::class, ['runId' => $run->id])->handle(app(\App\Services\EndpointResolver::class));

        $run->refresh();
        $item->refresh();
        $secondItem->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->resolved_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertSame('resolved', $item->status);
        $this->assertSame('https://example.com/', $item->resolved_url);
        $this->assertSame('example.com', $item->resolved_host);
        $this->assertSame('https', $item->resolved_scheme);
        $this->assertFalse($item->host_changed);
        $this->assertFalse($item->base_host_changed);
        $this->assertFalse($item->http_to_https_redirect);
        $this->assertSame('text/html', $item->content_type);
        $this->assertIsInt($item->response_time_ms);
        $this->assertSame('nginx', $item->platform_headers['server']);
        $this->assertTrue($item->security_headers['strict-transport-security']['present']);
        $this->assertSame(200, $item->last_status_code);
        $this->assertNotNull($item->last_checked_at);
        $this->assertSame('resolved', $secondItem->status);
        $this->assertSame('https://second.example/', $secondItem->resolved_url);
        $this->assertSame('second.example', $secondItem->resolved_host);
    }

    public function test_process_endpoint_resolution_run_marks_resolver_failures_as_unresolved(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'ftp://missing.example',
        ]);
        $run = EndpointResolutionRun::query()->create([
            'requested_count' => 1,
            'total_count' => 1,
            'status' => 'pending',
        ]);
        $item = $run->items()->create([
            'endpoint_id' => $endpoint->id,
            'position' => 1,
            'location' => $endpoint->location,
            'status' => 'queued',
        ]);

        app(ProcessEndpointResolutionRun::class, ['runId' => $run->id])->handle(app(\App\Services\EndpointResolver::class));

        $run->refresh();
        $item->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(0, $run->resolved_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertSame('unresolved', $item->status);
        $this->assertSame('unsupported_scheme', $item->failure_reason);
        $this->assertSame('unsupported', $item->failure_category);
        $this->assertNotNull($item->last_checked_at);
    }

    public function test_endpoint_pages_do_not_show_resolve_next_action(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.index'))
            ->assertOk()
            ->assertDontSee('Resolve Next');

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertDontSee('Resolve Next');
    }
}

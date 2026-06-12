<?php

namespace Tests\Feature;

use App\Jobs\ProcessEndpointResolutionRun;
use App\Models\Endpoint;
use App\Models\EndpointResolutionRun;
use App\Models\User;
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

    public function test_automation_page_shows_resolve_next_action(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('automation.index'))
            ->assertOk()
            ->assertSee('Automation')
            ->assertSee('Resolve Next')
            ->assertSee('Start Batch')
            ->assertSee(route('endpoints.resolve.next.store'), false)
            ->assertSee(route('automation.resolve-multiple.store'), false)
            ->assertSee('name="endpoint_count"', false)
            ->assertSee('Endpoint queue')
            ->assertSee('Last Checked At');
    }

    public function test_automation_page_shows_endpoint_queue_in_resolution_order(): void
    {
        $user = User::factory()->create();

        Endpoint::query()->create([
            'location' => 'checked.example',
            'last_checked_at' => now()->subYear(),
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
            'last_status_code' => 200,
            'last_checked_at' => now(),
        ]);
        $unresolvedItem = $run->items()->create([
            'position' => 2,
            'location' => 'missing.example',
            'status' => 'unresolved',
            'failure_reason' => 'dns_not_found',
            'last_checked_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('automation.runs.show', $run))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.status', 'resolved')
            ->assertJsonPath('items.0.resolved_url', 'https://example.com/')
            ->assertJsonPath('items.0.last_status_code', 200)
            ->assertJsonPath('items.1.id', $unresolvedItem->id)
            ->assertJsonPath('items.1.status', 'unresolved')
            ->assertJsonPath('items.1.presentation_status', 'unresolved')
            ->assertJsonPath('items.1.status_label', 'dns_not_found')
            ->assertJsonPath('items.1.failure_reason', 'dns_not_found');
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
            '*' => Http::response('ok', 200),
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
        $this->assertSame(200, $item->last_status_code);
        $this->assertNotNull($item->last_checked_at);
        $this->assertSame('resolved', $secondItem->status);
        $this->assertSame('https://second.example/', $secondItem->resolved_url);
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
            ->get(route('endpoints.resolve', $endpoint))
            ->assertOk()
            ->assertDontSee('Resolve Next');
    }
}

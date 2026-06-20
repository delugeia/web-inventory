<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EndpointRecheckFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resolver.skip_dns_check', true);
        config()->set('resolver.connect_timeout', 1);
        config()->set('resolver.request_timeout', 1);
    }

    public function test_endpoint_detail_page_shows_recheck_action_without_resolve_page_link(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'last_checked_at' => CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC'),
        ]);
        DB::table('endpoints')
            ->where('id', $endpoint->id)
            ->update([
                'created_at' => CarbonImmutable::parse('2026-06-11 15:30:00', 'UTC'),
                'updated_at' => CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC'),
            ]);
        $endpoint->refresh();

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSee('Recheck')
            ->assertSee('Fri, 12 Jun 2026, 5:50 PM')
            ->assertSee('Thu, 11 Jun 2026, 10:30 AM')
            ->assertSee(route('endpoints.resolve.store', $endpoint), false)
            ->assertDontSee('Resolve Endpoint')
            ->assertDontSee('href="'.url("/endpoints/{$endpoint->id}/resolve").'"', false);
    }

    public function test_endpoint_detail_page_shows_page_title_after_resolved_url(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_title' => 'Example Domain',
            'last_status_code' => 200,
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSeeInOrder([
                'Resolved URL',
                'https://example.com/',
                'Page Title',
                'Example Domain',
                'Last Status Code',
            ]);
    }

    public function test_single_endpoint_recheck_updates_endpoint_and_redirects_to_detail_page(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('endpoints.resolve.store', $endpoint));

        $response
            ->assertRedirect(route('endpoints.show', $endpoint))
            ->assertSessionHas('status', 'Rechecked example.com: resolved to https://example.com/ with status 200.');

        $endpoint->refresh();

        $this->assertSame('https://example.com/', $endpoint->resolved_url);
        $this->assertSame(200, $endpoint->last_status_code);
        $this->assertNotNull($endpoint->last_checked_at);
    }

    public function test_endpoint_index_displays_last_checked_at_with_normalized_format(): void
    {
        $user = User::factory()->create();

        Endpoint::query()->create([
            'location' => 'example.com',
            'last_checked_at' => CarbonImmutable::parse('2026-06-12 22:50:20', 'UTC'),
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.index'))
            ->assertOk()
            ->assertSee('Fri, 12 Jun 2026, 5:50 PM')
            ->assertDontSee('2026-06-12 22:50:20');
    }

    public function test_removed_resolve_page_returns_not_found(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        $this
            ->actingAs($user)
            ->get("/endpoints/{$endpoint->id}/resolve")
            ->assertNotFound();
    }
}

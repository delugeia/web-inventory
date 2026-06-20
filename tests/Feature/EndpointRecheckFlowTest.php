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

    public function test_endpoint_detail_page_shows_cached_copy_links_when_content_exists(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_content' => '<html><body>Cached</body></html>',
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
                'Cached Copy',
                'View',
                'View Source',
                'Page Title',
            ])
            ->assertSee(route('endpoints.cached', $endpoint), false)
            ->assertSee(route('endpoints.cached.source', $endpoint), false);
    }

    public function test_endpoint_detail_page_shows_cached_copy_unavailable_when_content_is_missing(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSeeInOrder([
                'Resolved URL',
                'https://example.com/',
                'Cached Copy',
                '--',
                'Page Title',
            ])
            ->assertDontSee(route('endpoints.cached', $endpoint), false)
            ->assertDontSee(route('endpoints.cached.source', $endpoint), false);
    }

    public function test_cached_copy_page_renders_wrapper_banner_and_sandboxed_iframe(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_content' => '<html><body>Cached</body></html>',
            'page_title' => 'Example Domain',
            'last_checked_at' => CarbonImmutable::parse('2026-06-20 18:15:00', 'UTC'),
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.cached', $endpoint))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Cached Page View (Sat, 20 Jun 2026, 1:15 PM)')
            ->assertSee('NOTE: External assets may load from the live site.')
            ->assertSee('bg-rose-50', false)
            ->assertSee(route('endpoints.cached.source', $endpoint), false)
            ->assertSee('View Cached Source')
            ->assertSee('https://example.com/', false)
            ->assertSee(route('endpoints.cached.content', $endpoint), false)
            ->assertSee('sandbox="allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"', false)
            ->assertDontSee('Endpoint Details')
            ->assertDontSee('Live URL');
    }

    public function test_cached_copy_content_returns_exact_cached_html(): void
    {
        $user = User::factory()->create();
        $html = '<html><head><title>Cached</title></head><body><a href="https://example.com/about">About</a></body></html>';
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'page_content' => $html,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('endpoints.cached.content', $endpoint));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame($html, $response->getContent());
    }

    public function test_cached_copy_source_renders_highlighted_source_viewer(): void
    {
        $user = User::factory()->create();
        $html = '<html><body><a href="https://example.com/about">About</a><script>alert("cached")</script></body></html>';
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_content' => $html,
            'last_checked_at' => CarbonImmutable::parse('2026-06-20 18:15:00', 'UTC'),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('endpoints.cached.source', $endpoint));

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Cached Source View (Sat, 20 Jun 2026, 1:15 PM)')
            ->assertSee('URLs have been updated to absolute.')
            ->assertSee('Line wrap')
            ->assertSee('bg-rose-50', false)
            ->assertSee(route('endpoints.cached', $endpoint), false)
            ->assertSee('View Cached Page')
            ->assertSee('line-numbers language-html', false)
            ->assertSee(e($html), false)
            ->assertDontSee($html, false)
            ->assertDontSee('Endpoint Details');
    }

    public function test_cached_copy_routes_return_not_found_without_cached_content(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        $this->actingAs($user)->get(route('endpoints.cached', $endpoint))->assertNotFound();
        $this->actingAs($user)->get(route('endpoints.cached.content', $endpoint))->assertNotFound();
        $this->actingAs($user)->get(route('endpoints.cached.source', $endpoint))->assertNotFound();
    }

    public function test_cached_copy_routes_require_authentication(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'page_content' => '<html><body>Cached</body></html>',
        ]);

        $this->get(route('endpoints.cached', $endpoint))->assertRedirect(route('login'));
        $this->get(route('endpoints.cached.content', $endpoint))->assertRedirect(route('login'));
        $this->get(route('endpoints.cached.source', $endpoint))->assertRedirect(route('login'));
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

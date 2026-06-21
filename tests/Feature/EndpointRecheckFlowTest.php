<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\EndpointPageAnalysis;
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

    public function test_endpoint_profile_page_shows_recheck_action_without_resolve_page_link(): void
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

    public function test_endpoint_profile_page_shows_page_title_after_resolved_url(): void
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

    public function test_endpoint_profile_header_shows_endpoint_name_under_location(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'cyclonehockey.com',
            'name' => 'Cyclone Hockey',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
            'last_status_code' => 200,
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSeeInOrder([
                'Back to Endpoints',
                'cyclonehockey.com',
                'Cyclone Hockey',
                'Edit',
                'Endpoint Profile',
                'Managed By',
                'Trademark',
                'DNS Provider',
                'Network Solutions, LLC',
            ]);
    }

    public function test_endpoint_profile_page_shows_cached_copy_links_when_content_exists(): void
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

    public function test_endpoint_profile_page_shows_cached_copy_links_when_rendered_content_exists(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'rendered_page_content' => '<html><body>Rendered</body></html>',
            'last_status_code' => 200,
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSee('Cached Copy')
            ->assertSee('View')
            ->assertSee('View Source')
            ->assertSee(route('endpoints.cached', $endpoint), false)
            ->assertSee(route('endpoints.cached.source', $endpoint), false);
    }

    public function test_endpoint_profile_page_shows_cached_copy_unavailable_when_content_is_missing(): void
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

    public function test_endpoint_profile_page_shows_miscellaneous_and_ai_details(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_title' => 'Example Domain',
            'page_content' => '<html><body>Legitimate organization content.</body></html>',
            'last_status_code' => 200,
            'last_checked_at' => CarbonImmutable::parse('2026-06-20 18:15:00', 'UTC'),
            'resolved_host' => 'example.com',
            'resolved_scheme' => 'https',
            'redirect_followed' => true,
            'redirect_count' => 1,
            'http_to_https_redirect' => true,
            'rendered_capture_status' => 'completed',
            'rendered_page_title' => 'Rendered Example Domain',
            'rendered_page_text' => 'Rendered legitimate organization content.',
            'rendered_page_content' => '<html><body>Rendered legitimate organization content.</body></html>',
            'rendered_capture_duration_ms' => 350,
            'rendered_captured_at' => CarbonImmutable::parse('2026-06-20 18:16:03', 'UTC'),
        ]);

        EndpointPageAnalysis::query()->create([
            'endpoint_id' => $endpoint->id,
            'status' => 'completed',
            'page_status' => 'NORMAL',
            'confidence' => 91,
            'summary' => 'The endpoint appears to contain legitimate public content.',
            'reasons' => ['Meaningful body content'],
            'signals' => ['page_title' => 'Example Domain'],
            'model' => 'gpt-5.5',
            'directive_version' => 'page-analysis:v1',
            'input_hash' => str_repeat('a', 64),
            'input_character_count' => 1200,
            'response_time_ms' => 840,
            'queued_at' => CarbonImmutable::parse('2026-06-20 18:16:00', 'UTC'),
            'started_at' => CarbonImmutable::parse('2026-06-20 18:16:01', 'UTC'),
            'completed_at' => CarbonImmutable::parse('2026-06-20 18:16:02', 'UTC'),
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSee('Miscellaneous Details')
            ->assertSee('Redirect Followed')
            ->assertSee('Cached Content Length')
            ->assertSee('Rendered Capture Status')
            ->assertSee('Rendered Example Domain')
            ->assertSee('Rendered Text Length')
            ->assertSee('AI Details')
            ->assertSee('Prepared Input Status')
            ->assertSee('Prepared Input Hash')
            ->assertSee('NORMAL')
            ->assertSee('The endpoint appears to contain legitimate public content.')
            ->assertSee('Meaningful body content')
            ->assertSee('Prepared Input JSON')
            ->assertSee('Line wrap')
            ->assertSee('<details class="border-t border-slate-200">', false)
            ->assertDontSee('<details class="border-t border-slate-200" open>', false)
            ->assertSee('source-code line-numbers language-json', false)
            ->assertSee('x-bind:class="{ \'source-wrap\': wrapPreparedInput }"', false)
            ->assertSee('&quot;task&quot;: &quot;Evaluate the final resolved webpage content', false)
            ->assertSee('Rendered legitimate organization content.');
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
            ->assertDontSee('Endpoint Profile')
            ->assertDontSee('Live URL');
    }

    public function test_cached_copy_content_returns_rendered_html_when_available(): void
    {
        $user = User::factory()->create();
        $html = '<html><head><title>Cached</title></head><body>Original cached content</body></html>';
        $renderedHtml = '<html><head><title>Rendered</title></head><body>Rendered browser content</body></html>';
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'page_content' => $html,
            'rendered_page_content' => $renderedHtml,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('endpoints.cached.content', $endpoint));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame($renderedHtml, $response->getContent());
    }

    public function test_cached_copy_content_falls_back_to_original_cached_html(): void
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

        $response->assertOk();
        $this->assertSame($html, $response->getContent());
    }

    public function test_cached_copy_source_renders_highlighted_source_viewer(): void
    {
        $user = User::factory()->create();
        $html = '<html><body><a href="https://example.com/about">About</a><script>alert("cached")</script></body></html>';
        $renderedHtml = '<html><body><h1>Rendered Source</h1><script>alert("rendered")</script></body></html>';
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_content' => $html,
            'rendered_page_content' => $renderedHtml,
            'last_checked_at' => CarbonImmutable::parse('2026-06-20 18:15:00', 'UTC'),
            'rendered_captured_at' => CarbonImmutable::parse('2026-06-20 18:16:00', 'UTC'),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('endpoints.cached.source', $endpoint));

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Rendered Source View (Sat, 20 Jun 2026, 1:16 PM)')
            ->assertDontSee('URLs have been updated to absolute.')
            ->assertSee('Line wrap')
            ->assertSee('bg-rose-50', false)
            ->assertSee(route('endpoints.cached', $endpoint), false)
            ->assertSee('View Rendered Page')
            ->assertSee('line-numbers language-html', false)
            ->assertSee(e($renderedHtml), false)
            ->assertDontSee(e($html), false)
            ->assertDontSee($html, false)
            ->assertDontSee('Endpoint Profile');
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

    public function test_captured_header_names_do_not_render_as_layout_page_heading(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'platform_headers' => [
                'server' => 'nginx',
            ],
            'security_headers' => [
                'strict-transport-security' => ['present' => true],
                'permissions-policy' => ['present' => false],
            ],
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertSee('Endpoint Profile')
            ->assertSee('server')
            ->assertSee('permissions-policy: missing')
            ->assertDontSee('<header class="bg-white shadow">', false);
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

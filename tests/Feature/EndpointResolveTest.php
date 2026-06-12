<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Services\EndpointResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EndpointResolveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resolver.skip_dns_check', true);
        config()->set('resolver.connect_timeout', 1);
        config()->set('resolver.request_timeout', 1);
    }

    public function test_redirect_is_followed_and_chain_is_persisted(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'https://example.com/') {
                return Http::response('', 301, [
                    'Location' => 'https://www.example.com/welcome',
                ]);
            }

            if ($url === 'https://www.example.com/welcome') {
                return Http::response('ok', 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Server' => 'nginx',
                    'X-Powered-By' => 'PHP/8.5',
                    'Strict-Transport-Security' => 'max-age=31536000',
                    'X-Frame-Options' => 'SAMEORIGIN',
                ]);
            }

            return Http::response('', 404);
        });

        $result = app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertTrue($result['resolved']);
        $this->assertSame('https://www.example.com/welcome', $result['resolved_url']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('www.example.com', $endpoint->resolved_host);
        $this->assertSame('https', $endpoint->resolved_scheme);
        $this->assertTrue($endpoint->host_changed);
        $this->assertFalse($endpoint->base_host_changed);
        $this->assertFalse($endpoint->http_to_https_redirect);
        $this->assertSame('text/html; charset=UTF-8', $endpoint->content_type);
        $this->assertIsInt($endpoint->response_time_ms);
        $this->assertSame('nginx', $endpoint->platform_headers['server']);
        $this->assertSame('PHP/8.5', $endpoint->platform_headers['x-powered-by']);
        $this->assertTrue($endpoint->security_headers['strict-transport-security']['present']);
        $this->assertTrue($endpoint->security_headers['x-frame-options']['present']);
        $this->assertFalse($endpoint->security_headers['content-security-policy']['present']);
        $this->assertNull($endpoint->failure_category);
        $this->assertTrue($endpoint->redirect_followed);
        $this->assertSame(1, $endpoint->redirect_count);
        $this->assertIsArray($endpoint->redirect_chain);
        $this->assertCount(2, $endpoint->redirect_chain);
        $this->assertSame(301, $endpoint->redirect_chain[0]['status_code']);
        $this->assertSame('https://example.com/', $endpoint->redirect_chain[0]['url']);
        $this->assertSame('https://www.example.com/welcome', $endpoint->redirect_chain[0]['location']);
        $this->assertSame(200, $endpoint->redirect_chain[1]['status_code']);
        $this->assertSame('https://www.example.com/welcome', $endpoint->redirect_chain[1]['url']);
        $this->assertNull($endpoint->redirect_chain[1]['location']);
    }

    public function test_redirect_loop_is_detected(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'https://loop.example/',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'https://loop.example/') {
                return Http::response('', 302, [
                    'Location' => '/',
                ]);
            }

            return Http::response('', 404);
        });

        $result = app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertFalse($result['resolved']);
        $this->assertSame('redirect_loop', $result['failure_reason']);
        $this->assertSame('redirect_loop', $endpoint->failure_reason);
        $this->assertTrue($endpoint->redirect_followed);
        $this->assertSame(1, $endpoint->redirect_count);
        $this->assertIsArray($endpoint->redirect_chain);
        $this->assertCount(1, $endpoint->redirect_chain);
    }

    public function test_redirect_max_hops_is_enforced(): void
    {
        config()->set('resolver.redirect_max_hops', 1);

        $endpoint = Endpoint::query()->create([
            'location' => 'https://hops.example/',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'https://hops.example/') {
                return Http::response('', 302, [
                    'Location' => '/a',
                ]);
            }

            if ($url === 'https://hops.example/a') {
                return Http::response('', 302, [
                    'Location' => '/b',
                ]);
            }

            return Http::response('', 404);
        });

        $result = app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertFalse($result['resolved']);
        $this->assertSame('redirect_max_hops_exceeded', $result['failure_reason']);
        $this->assertSame('redirect_max_hops_exceeded', $endpoint->failure_reason);
        $this->assertTrue($endpoint->redirect_followed);
        $this->assertSame(2, $endpoint->redirect_count);
        $this->assertIsArray($endpoint->redirect_chain);
        $this->assertCount(2, $endpoint->redirect_chain);
    }

    public function test_http_to_https_redirect_is_detected(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'http://example.com/',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'http://example.com/') {
                return Http::response('', 301, [
                    'Location' => 'https://example.com/',
                ]);
            }

            return Http::response('ok', 200);
        });

        app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertTrue($endpoint->http_to_https_redirect);
        $this->assertFalse($endpoint->host_changed);
        $this->assertFalse($endpoint->base_host_changed);
        $this->assertSame('https', $endpoint->resolved_scheme);
    }

    public function test_domain_resolve_captures_canonical_url_coverage(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'http://example.com/') {
                return Http::response('', 301, [
                    'Location' => 'https://example.com/',
                ]);
            }

            if ($url === 'http://www.example.com/') {
                return Http::response('', 301, [
                    'Location' => 'https://www.example.com/',
                ]);
            }

            if ($url === 'https://www.example.com/') {
                return Http::response('', 301, [
                    'Location' => 'https://example.com/',
                ]);
            }

            if ($url === 'https://example.com/') {
                return Http::response('ok', 200);
            }

            return Http::response('', 404);
        });

        app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertSame('https://example.com/', $endpoint->resolved_url);
        $this->assertSame('pass', $endpoint->canonical_url_check['status']);
        $this->assertSame('https://example.com/', $endpoint->canonical_url_check['preferred_url']);
        $this->assertCount(4, $endpoint->canonical_url_check['variants']);
        $this->assertSame(
            ['forces_https', 'forces_https', 'canonical', 'to_canonical'],
            array_column($endpoint->canonical_url_check['variants'], 'result')
        );
        $this->assertSame(2, $endpoint->canonical_url_check['variants'][1]['redirect_count']);
    }

    public function test_cross_domain_redirect_sets_host_change_flags(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'https://example.com/',
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === 'https://example.com/') {
                return Http::response('', 302, [
                    'Location' => 'https://vendor.example.net/',
                ]);
            }

            return Http::response('ok', 200);
        });

        app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertSame('vendor.example.net', $endpoint->resolved_host);
        $this->assertTrue($endpoint->host_changed);
        $this->assertTrue($endpoint->base_host_changed);
    }

    public function test_failure_reason_sets_failure_category(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'ftp://missing.example',
        ]);

        $result = app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertFalse($result['resolved']);
        $this->assertSame('unsupported_scheme', $endpoint->failure_reason);
        $this->assertSame('unsupported', $endpoint->failure_category);
        $this->assertNull($endpoint->resolved_host);
        $this->assertNull($endpoint->platform_headers);
        $this->assertNull($endpoint->security_headers);
    }
}

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
                return Http::response('ok', 200);
            }

            return Http::response('', 404);
        });

        $result = app(EndpointResolver::class)->resolve($endpoint);
        $endpoint->refresh();

        $this->assertTrue($result['resolved']);
        $this->assertSame('https://www.example.com/welcome', $result['resolved_url']);
        $this->assertSame(200, $result['status_code']);
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
}

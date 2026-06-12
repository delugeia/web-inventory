<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EndpointResolveNextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resolver.skip_dns_check', true);
        config()->set('resolver.connect_timeout', 1);
        config()->set('resolver.request_timeout', 1);
    }

    public function test_resolve_next_selects_never_checked_endpoint_by_location(): void
    {
        $user = User::factory()->create();
        $checked = Endpoint::query()->create([
            'location' => 'checked.example',
            'last_checked_at' => now()->subYear(),
        ]);
        $zeta = Endpoint::query()->create([
            'location' => 'zeta.example',
        ]);
        $alpha = Endpoint::query()->create([
            'location' => 'alpha.example',
        ]);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('endpoints.resolve.next.store'));

        $response
            ->assertRedirect(route('endpoints.resolve', $alpha))
            ->assertSessionHas('status', 'Resolved next endpoint alpha.example to https://alpha.example/ with status 200.');

        $alpha->refresh();
        $zeta->refresh();
        $checked->refresh();

        $this->assertNotNull($alpha->last_checked_at);
        $this->assertNull($zeta->last_checked_at);
        $this->assertSame('checked.example', $checked->location);
    }

    public function test_resolve_next_selects_oldest_checked_endpoint_when_all_have_been_checked(): void
    {
        $user = User::factory()->create();
        $newer = Endpoint::query()->create([
            'location' => 'newer.example',
            'last_checked_at' => now()->subDay(),
        ]);
        $older = Endpoint::query()->create([
            'location' => 'older.example',
            'last_checked_at' => now()->subDays(7),
        ]);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('endpoints.resolve.next.store'));

        $response
            ->assertRedirect(route('endpoints.resolve', $older))
            ->assertSessionHas('status', 'Resolved next endpoint older.example to https://older.example/ with status 200.');

        $older->refresh();
        $newer->refresh();

        $this->assertTrue($older->last_checked_at->greaterThan($newer->last_checked_at));
    }

    public function test_resolve_next_redirects_to_automation_when_no_endpoints_exist(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('endpoints.resolve.next.store'));

        $response
            ->assertRedirect(route('automation.index'))
            ->assertSessionHas('status', 'There are no endpoints to resolve.');
    }

    public function test_resolve_page_does_not_check_endpoint_on_get(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'unchecked.example',
        ]);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.resolve', $endpoint))
            ->assertOk();

        $endpoint->refresh();

        $this->assertNull($endpoint->last_checked_at);
    }
}

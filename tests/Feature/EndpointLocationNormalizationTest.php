<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointLocationNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_store_normalizes_fully_qualified_urls(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('endpoints.store'), [
            'location' => '  HTTPS://Example.COM/Path/To/Page?Arg=Value  ',
            'name' => 'Example Website',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ])->assertRedirect();

        $this->assertDatabaseHas('endpoints', [
            'location' => 'https://example.com/Path/To/Page?Arg=Value',
            'name' => 'Example Website',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ]);

        $endpoint = Endpoint::query()->where('location', 'https://example.com/Path/To/Page?Arg=Value')->firstOrFail();
        $response->assertRedirect(route('endpoints.show', $endpoint));
    }

    public function test_update_normalizes_domain_names(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'managed_by' => 'Initial Office',
            'dns_provider' => 'Initial DNS',
        ]);

        $this->actingAs($user)->patch(route('endpoints.update', $endpoint), [
            'location' => '  EXAMPLE.ORG  ',
            'name' => 'Updated Website',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ])->assertRedirect(route('endpoints.show', $endpoint));

        $this->assertDatabaseHas('endpoints', [
            'id' => $endpoint->id,
            'location' => 'example.org',
            'name' => 'Updated Website',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ]);
    }

    public function test_create_endpoint_page_shows_optional_name_field(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('endpoints.create'))
            ->assertOk()
            ->assertSee('Create Endpoint')
            ->assertSee('name="location"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="managed_by"', false)
            ->assertSee('name="dns_provider"', false);
    }

    public function test_edit_endpoint_page_shows_name_management_and_dns_provider_fields(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'name' => 'Example Website',
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ]);

        $this
            ->actingAs($user)
            ->get(route('endpoints.edit', $endpoint))
            ->assertOk()
            ->assertSee('Edit Endpoint')
            ->assertSee('name="name"', false)
            ->assertSee('value="Example Website"', false)
            ->assertSee('name="managed_by"', false)
            ->assertSee('value="Trademark"', false)
            ->assertSee('name="dns_provider"', false)
            ->assertSee('value="Network Solutions, LLC"', false);
    }

    public function test_bulk_import_normalizes_locations(): void
    {
        $user = User::factory()->create();

        $lines = implode("\n", [
            '  HTTPS://Example.COM/SomePath?X=Y  Trademark - Network Solutions, LLC',
            '  EXAMPLE.NET  WebDev - DreamHost, LLC',
        ]);

        $this->actingAs($user)->post(route('endpoints.import.store'), [
            'lines' => $lines,
        ])->assertRedirect(route('endpoints.index'));

        $this->assertDatabaseHas('endpoints', [
            'location' => 'https://example.com/SomePath?X=Y',
            'name' => null,
            'managed_by' => 'Trademark',
            'dns_provider' => 'Network Solutions, LLC',
        ]);

        $this->assertDatabaseHas('endpoints', [
            'location' => 'example.net',
            'name' => null,
            'managed_by' => 'WebDev',
            'dns_provider' => 'DreamHost, LLC',
        ]);
    }
}

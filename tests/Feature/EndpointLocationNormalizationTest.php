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

        $this->actingAs($user)->post(route('endpoints.store'), [
            'location' => '  HTTPS://Example.COM/Path/To/Page?Arg=Value  ',
            'name' => 'Example Endpoint',
        ])->assertRedirect(route('endpoints.index'));

        $this->assertDatabaseHas('endpoints', [
            'location' => 'https://example.com/Path/To/Page?Arg=Value',
            'name' => 'Example Endpoint',
        ]);
    }

    public function test_update_normalizes_domain_names(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'name' => 'Initial',
        ]);

        $this->actingAs($user)->patch(route('endpoints.update', $endpoint), [
            'location' => '  EXAMPLE.ORG  ',
            'name' => 'Updated',
        ])->assertRedirect(route('endpoints.index'));

        $this->assertDatabaseHas('endpoints', [
            'id' => $endpoint->id,
            'location' => 'example.org',
            'name' => 'Updated',
        ]);
    }

    public function test_bulk_import_normalizes_locations(): void
    {
        $user = User::factory()->create();

        $lines = implode("\n", [
            '  HTTPS://Example.COM/SomePath?X=Y  Bulk One',
            '  EXAMPLE.NET  Bulk Two',
        ]);

        $this->actingAs($user)->post(route('endpoints.import.store'), [
            'lines' => $lines,
        ])->assertRedirect(route('endpoints.index'));

        $this->assertDatabaseHas('endpoints', [
            'location' => 'https://example.com/SomePath?X=Y',
            'name' => 'Bulk One',
        ]);

        $this->assertDatabaseHas('endpoints', [
            'location' => 'example.net',
            'name' => 'Bulk Two',
        ]);
    }
}

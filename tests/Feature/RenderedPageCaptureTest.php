<?php

namespace Tests\Feature;

use App\Jobs\CaptureEndpointRenderedPage;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\RenderedPageCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RenderedPageCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_capture_service_stores_browser_rendered_evidence(): void
    {
        config([
            'rendered_capture.enabled' => true,
            'rendered_capture.node_binary' => PHP_BINARY,
            'rendered_capture.script_path' => base_path('tests/Fixtures/render-page-success.php'),
            'rendered_capture.timeout_seconds' => 3,
        ]);

        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_checked_at' => now(),
        ]);

        $result = app(RenderedPageCaptureService::class)->capture($endpoint);

        $endpoint->refresh();

        $this->assertTrue($result->captured);
        $this->assertSame('completed', $endpoint->rendered_capture_status);
        $this->assertSame('Rendered Test Page', $endpoint->rendered_page_title);
        $this->assertStringContainsString('Under Construction', $endpoint->rendered_page_text);
        $this->assertStringContainsString('<h1>This Page Is Under Construction</h1>', $endpoint->rendered_page_content);
        $this->assertSame(321, $endpoint->rendered_capture_duration_ms);
        $this->assertNull($endpoint->rendered_capture_error);
        $this->assertNotNull($endpoint->rendered_captured_at);
    }

    public function test_rendered_capture_button_queues_job_when_enabled(): void
    {
        Queue::fake();
        config(['rendered_capture.enabled' => true]);

        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_checked_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('endpoints.rendered-capture.store', $endpoint))
            ->assertRedirect(route('endpoints.show', $endpoint))
            ->assertSessionHas('status', 'Queued rendered capture for example.com.');

        Queue::assertPushed(CaptureEndpointRenderedPage::class, fn ($job) => $job->endpointId === $endpoint->id);
        $this->assertSame('queued', $endpoint->refresh()->rendered_capture_status);
    }

    public function test_rendered_capture_button_reports_disabled_state(): void
    {
        Queue::fake();
        config(['rendered_capture.enabled' => false]);

        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create(['location' => 'example.com']);

        $this
            ->actingAs($user)
            ->post(route('endpoints.rendered-capture.store', $endpoint))
            ->assertRedirect(route('endpoints.show', $endpoint))
            ->assertSessionHas('status', 'Rendered capture is disabled. Enable RENDERED_CAPTURE_ENABLED to use it.');

        Queue::assertNothingPushed();
    }
}

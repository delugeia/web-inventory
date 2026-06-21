<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeEndpointPage;
use App\Models\Endpoint;
use App\Models\EndpointPageAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EndpointPageAnalysisFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_page_button_queues_analysis_when_enabled(): void
    {
        Queue::fake();
        config([
            'services.openai.analysis_enabled' => true,
            'services.openai.model' => 'gpt-test',
        ]);

        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_checked_at' => now(),
            'last_status_code' => 200,
            'page_title' => 'Example Domain',
            'page_content' => '<html><body>Normal public content.</body></html>',
        ]);

        $this
            ->actingAs($user)
            ->post(route('endpoints.page-analysis.store', $endpoint))
            ->assertRedirect(route('endpoints.show', $endpoint))
            ->assertSessionHas('status', 'Queued AI page analysis for example.com.');

        $analysis = EndpointPageAnalysis::query()->where('endpoint_id', $endpoint->id)->firstOrFail();

        $this->assertSame('queued', $analysis->status);
        $this->assertSame('gpt-test', $analysis->model);
        $this->assertNotNull($analysis->input_hash);
        $this->assertSame('page-analysis:v1', $analysis->directive_version);

        Queue::assertPushed(AnalyzeEndpointPage::class, fn ($job) => $job->analysisId === $analysis->id);
    }

    public function test_analyze_page_button_reports_disabled_state(): void
    {
        Queue::fake();
        config(['services.openai.analysis_enabled' => false]);

        $user = User::factory()->create();
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_checked_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('endpoints.page-analysis.store', $endpoint))
            ->assertRedirect(route('endpoints.show', $endpoint))
            ->assertSessionHas('status', 'AI page analysis is disabled. Enable OPENAI_ANALYSIS_ENABLED to use it.');

        Queue::assertNothingPushed();
        $this->assertSame(0, EndpointPageAnalysis::query()->count());
    }

    public function test_analyze_endpoint_page_job_stores_openai_result(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-test',
            'services.openai.timeout_seconds' => 5,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_parked',
                'output_text' => json_encode([
                    'status' => 'PARKED',
                    'summary' => 'Looks like a parked page with generic marketing links.',
                    'confidence' => 94,
                    'reasons' => ['Under construction page', 'Generic related search links'],
                ]),
            ]),
        ]);

        $endpoint = Endpoint::query()->create([
            'location' => 'cyclone.bet',
            'resolved_url' => 'http://cyclone.bet/',
            'last_checked_at' => now(),
            'last_status_code' => 200,
            'rendered_capture_status' => 'completed',
            'rendered_page_title' => 'Cyclone.bet',
            'rendered_page_text' => 'cyclone.bet This Page Is Under Construction Related Searches Refinancing Loans',
            'rendered_page_content' => '<html><body>This Page Is Under Construction</body></html>',
        ]);
        $input = app(\App\Services\PageAnalysisInputBuilder::class)->build($endpoint);

        $analysis = EndpointPageAnalysis::query()->create([
            'endpoint_id' => $endpoint->id,
            'status' => 'queued',
            'model' => 'gpt-test',
            'directive_version' => $input->payload['directive_version'],
            'input_hash' => $input->inputHash,
            'input_character_count' => $input->inputCharacterCount,
            'queued_at' => now(),
        ]);

        (new AnalyzeEndpointPage($analysis->id))->handle(app(\App\Services\PageAnalysisInputBuilder::class), app(\App\Services\OpenAiPageAnalysisService::class));

        $analysis->refresh();

        $this->assertSame('completed', $analysis->status);
        $this->assertSame('PARKED', $analysis->page_status);
        $this->assertSame('Looks like a parked page with generic marketing links.', $analysis->summary);
        $this->assertSame(94, $analysis->confidence);
        $this->assertSame(['Under construction page', 'Generic related search links'], $analysis->reasons);
        $this->assertSame('resp_parked', $analysis->signals['openai_response_id']);
        $this->assertNotNull($analysis->completed_at);
        $this->assertNull($analysis->failed_at);
    }

    public function test_analyze_endpoint_page_job_fails_stale_queued_input(): void
    {
        Http::fake();

        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_checked_at' => now(),
            'last_status_code' => 200,
            'page_title' => 'Example Domain',
            'page_content' => '<html><body>Current content.</body></html>',
        ]);

        $analysis = EndpointPageAnalysis::query()->create([
            'endpoint_id' => $endpoint->id,
            'status' => 'queued',
            'directive_version' => 'page-analysis:v1',
            'input_hash' => str_repeat('d', 64),
            'input_character_count' => 1200,
            'queued_at' => now(),
        ]);

        (new AnalyzeEndpointPage($analysis->id))->handle(app(\App\Services\PageAnalysisInputBuilder::class), app(\App\Services\OpenAiPageAnalysisService::class));

        $analysis->refresh();

        $this->assertSame('failed', $analysis->status);
        $this->assertSame('stale_input', $analysis->failure_category);
        $this->assertSame('Endpoint changed after this analysis was queued.', $analysis->error_message);
        Http::assertNothingSent();
    }
}

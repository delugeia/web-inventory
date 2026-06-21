<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\EndpointPageAnalysis;
use App\Models\EndpointResolutionRun;
use App\Models\EndpointResolutionRunItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointPageAnalysisDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_page_analysis_can_store_ai_result_metadata(): void
    {
        $endpoint = Endpoint::query()->create([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'page_title' => 'Example Domain',
            'page_content' => '<html><title>Example Domain</title><body>Example content.</body></html>',
            'last_status_code' => 200,
            'last_checked_at' => now(),
        ]);

        $analysis = EndpointPageAnalysis::query()->create([
            'endpoint_id' => $endpoint->id,
            'status' => 'completed',
            'page_status' => 'NORMAL',
            'confidence' => 88,
            'summary' => 'The page contains normal public-facing content.',
            'reasons' => ['Has a meaningful title', 'Contains body copy'],
            'signals' => ['title' => 'Example Domain', 'status_code' => 200],
            'model' => 'gpt-5.5',
            'directive_version' => 'page-analysis:v1',
            'input_hash' => hash('sha256', 'example-input'),
            'input_character_count' => 512,
            'response_time_ms' => 1200,
            'queued_at' => now()->subSeconds(3),
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
        ]);

        $endpoint->refresh();

        $this->assertTrue($endpoint->pageAnalyses->first()->is($analysis));
        $this->assertTrue($endpoint->latestPageAnalysis->is($analysis));
        $this->assertSame('NORMAL', $analysis->page_status);
        $this->assertSame(['Has a meaningful title', 'Contains body copy'], $analysis->reasons);
        $this->assertSame('Example Domain', $analysis->signals['title']);
    }

    public function test_endpoint_page_analysis_can_reference_resolution_run_item(): void
    {
        $endpoint = Endpoint::query()->create(['location' => 'example.com']);

        $run = EndpointResolutionRun::query()->create([
            'requested_count' => 1,
            'total_count' => 1,
            'resolved_count' => 1,
            'failed_count' => 0,
            'status' => 'completed',
        ]);

        $item = EndpointResolutionRunItem::query()->create([
            'endpoint_resolution_run_id' => $run->id,
            'endpoint_id' => $endpoint->id,
            'position' => 1,
            'location' => $endpoint->location,
            'status' => 'resolved',
        ]);

        $analysis = EndpointPageAnalysis::query()->create([
            'endpoint_id' => $endpoint->id,
            'endpoint_resolution_run_item_id' => $item->id,
            'status' => 'pending',
            'queued_at' => now(),
        ]);

        $this->assertTrue($analysis->resolutionRunItem->is($item));
        $this->assertTrue($item->pageAnalyses->first()->is($analysis));
    }
}

<?php

namespace App\Jobs;

use App\Models\EndpointPageAnalysis;
use App\Services\OpenAiPageAnalysisService;
use App\Services\PageAnalysisInputBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AnalyzeEndpointPage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $analysisId,
    ) {}

    public function handle(PageAnalysisInputBuilder $inputBuilder, OpenAiPageAnalysisService $analysisService): void
    {
        $analysis = EndpointPageAnalysis::query()->with('endpoint')->find($this->analysisId);

        if ($analysis === null || $analysis->endpoint === null) {
            return;
        }

        $input = $inputBuilder->build($analysis->endpoint);

        if (! $input->shouldAnalyze) {
            $analysis->update([
                'status' => 'skipped',
                'failure_category' => $input->skipReason,
                'error_message' => $input->skipReason,
                'failed_at' => now(),
            ]);

            return;
        }

        if ($analysis->input_hash !== $input->inputHash || $analysis->directive_version !== ($input->payload['directive_version'] ?? null)) {
            $analysis->update([
                'status' => 'failed',
                'failure_category' => 'stale_input',
                'error_message' => 'Endpoint changed after this analysis was queued.',
                'failed_at' => now(),
            ]);

            return;
        }

        $analysis->update([
            'status' => 'running',
            'started_at' => now(),
            'failure_category' => null,
            'error_message' => null,
        ]);

        $startedAt = microtime(true);

        try {
            $result = $analysisService->analyze($input);

            $analysis->update([
                'status' => 'completed',
                'page_status' => $result->pageStatus,
                'summary' => $result->summary,
                'confidence' => $result->confidence,
                'reasons' => $result->reasons,
                'signals' => $result->signals,
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'failure_category' => null,
                'error_message' => null,
                'completed_at' => now(),
                'failed_at' => null,
            ]);
        } catch (Throwable $throwable) {
            $analysis->update([
                'status' => 'failed',
                'failure_category' => 'openai_error',
                'error_message' => mb_substr($throwable->getMessage() ?: 'openai_error', 0, 1024, 'UTF-8'),
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'failed_at' => now(),
            ]);
        }
    }
}

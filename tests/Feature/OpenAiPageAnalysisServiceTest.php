<?php

namespace Tests\Feature;

use App\Services\OpenAiPageAnalysisService;
use App\Services\PageAnalysisInput;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiPageAnalysisServiceTest extends TestCase
{
    public function test_openai_page_analysis_service_requests_strict_json_schema_and_returns_result(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-test',
            'services.openai.timeout_seconds' => 5,
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output' => [
                    [
                        'content' => [
                            [
                                'text' => json_encode([
                                    'status' => 'PARKED',
                                    'summary' => 'Looks like a parked page with generic marketing links.',
                                    'confidence' => 92,
                                    'reasons' => ['Under construction wording', 'Generic ad links'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $input = new PageAnalysisInput(
            shouldAnalyze: true,
            skipReason: null,
            payload: ['directive_version' => 'page-analysis:v1'],
            inputText: '{"task":"Evaluate"}',
            inputHash: str_repeat('b', 64),
            inputCharacterCount: 19,
        );

        $result = app(OpenAiPageAnalysisService::class)->analyze($input);

        $this->assertSame('PARKED', $result->pageStatus);
        $this->assertSame('Looks like a parked page with generic marketing links.', $result->summary);
        $this->assertSame(92, $result->confidence);
        $this->assertSame(['Under construction wording', 'Generic ad links'], $result->reasons);
        $this->assertSame('resp_123', $result->signals['openai_response_id']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $payload['model'] === 'gpt-test'
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && $payload['text']['format']['schema']['properties']['status']['enum'] === ['NORMAL', 'RESTRICTED', 'PARKED', 'BROKEN', 'OTHER'];
        });
    }
}

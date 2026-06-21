<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiPageAnalysisService
{
    private const ALLOWED_STATUSES = ['NORMAL', 'RESTRICTED', 'PARKED', 'BROKEN', 'OTHER'];

    public function analyze(PageAnalysisInput $input): PageAnalysisResult
    {
        if (! $input->shouldAnalyze) {
            throw new RuntimeException('page_analysis_input_skipped');
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if (trim($apiKey) === '') {
            throw new RuntimeException('missing_openai_api_key');
        }

        $startedAt = microtime(true);
        $response = $this->client($apiKey)
            ->timeout(max(1, (int) config('services.openai.timeout_seconds', 30)))
            ->post('https://api.openai.com/v1/responses', $this->requestPayload($input));

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?: 'openai_request_failed');
            throw new RuntimeException(mb_substr($message, 0, 1024, 'UTF-8'));
        }

        $json = $this->extractOutputJson($response->json());
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('invalid_openai_json_response');
        }

        $result = $this->validateResult($decoded);

        return new PageAnalysisResult(
            pageStatus: $result['status'],
            summary: $result['summary'],
            confidence: $result['confidence'],
            reasons: $result['reasons'],
            signals: [
                'openai_response_id' => $response->json('id'),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
        );
    }

    private function client(string $apiKey): PendingRequest
    {
        $client = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson();

        $organization = trim((string) config('services.openai.organization', ''));
        if ($organization !== '') {
            $client = $client->withHeaders(['OpenAI-Organization' => $organization]);
        }

        $project = trim((string) config('services.openai.project', ''));
        if ($project !== '') {
            $client = $client->withHeaders(['OpenAI-Project' => $project]);
        }

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(PageAnalysisInput $input): array
    {
        return [
            'model' => (string) config('services.openai.model', 'gpt-5.5'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => 'You evaluate one resolved webpage at a time for Web Inventory. Return only JSON that matches the requested schema.',
                ],
                [
                    'role' => 'user',
                    'content' => $input->inputText,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'webpage_analysis',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['status', 'summary', 'confidence', 'reasons'],
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => self::ALLOWED_STATUSES,
                            ],
                            'summary' => [
                                'type' => 'string',
                                'minLength' => 1,
                                'maxLength' => 512,
                            ],
                            'confidence' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'reasons' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 3,
                                'items' => [
                                    'type' => 'string',
                                    'minLength' => 1,
                                    'maxLength' => 240,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractOutputJson(array $response): string
    {
        $outputText = Arr::get($response, 'output_text');
        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        foreach ((array) ($response['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                $text = $content['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        throw new RuntimeException('missing_openai_output_text');
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{status: string, summary: string, confidence: int, reasons: array<int, string>}
     */
    private function validateResult(array $decoded): array
    {
        $status = $decoded['status'] ?? null;
        if (! is_string($status) || ! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('invalid_page_status');
        }

        $summary = $this->cleanString($decoded['summary'] ?? null, 1024);
        if ($summary === null) {
            throw new RuntimeException('missing_summary');
        }

        $confidence = $decoded['confidence'] ?? null;
        if (! is_int($confidence)) {
            throw new RuntimeException('invalid_confidence');
        }

        $reasons = $decoded['reasons'] ?? null;
        if (! is_array($reasons) || $reasons === []) {
            throw new RuntimeException('missing_reasons');
        }

        $cleanReasons = [];
        foreach (array_slice($reasons, 0, 3) as $reason) {
            $cleanReason = $this->cleanString($reason, 240);
            if ($cleanReason !== null) {
                $cleanReasons[] = $cleanReason;
            }
        }

        if ($cleanReasons === []) {
            throw new RuntimeException('missing_reasons');
        }

        return [
            'status' => $status,
            'summary' => $summary,
            'confidence' => max(0, min(100, $confidence)),
            'reasons' => $cleanReasons,
        ];
    }

    private function cleanString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value !== '' ? mb_substr($value, 0, $maxLength, 'UTF-8') : null;
    }
}

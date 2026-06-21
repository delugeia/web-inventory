<?php

namespace App\Services;

use App\Models\Endpoint;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class RenderedPageCaptureService
{
    public function capture(Endpoint $endpoint): RenderedPageCaptureResult
    {
        if (! (bool) config('rendered_capture.enabled', false)) {
            return new RenderedPageCaptureResult(false, 'skipped', 'rendered_capture_disabled');
        }

        if (! is_string($endpoint->resolved_url) || trim($endpoint->resolved_url) === '') {
            $endpoint->update([
                'rendered_capture_status' => 'skipped',
                'rendered_capture_error' => 'missing_resolved_url',
                'rendered_captured_at' => now(),
            ]);

            return new RenderedPageCaptureResult(false, 'skipped', 'missing_resolved_url');
        }

        $scriptPath = (string) config('rendered_capture.script_path');
        if (! File::exists($scriptPath)) {
            $endpoint->update([
                'rendered_capture_status' => 'failed',
                'rendered_capture_error' => 'render_script_missing',
                'rendered_captured_at' => now(),
            ]);

            return new RenderedPageCaptureResult(false, 'failed', 'render_script_missing');
        }

        $endpoint->update([
            'rendered_capture_status' => 'running',
            'rendered_capture_error' => null,
        ]);

        $timeoutSeconds = max(1, (int) config('rendered_capture.timeout_seconds', 12));
        $process = new Process([
            (string) config('rendered_capture.node_binary', 'node'),
            $scriptPath,
        ], base_path());

        $process->setTimeout($timeoutSeconds + 5);
        $process->setInput(json_encode([
            'url' => $endpoint->resolved_url,
            'timeoutMs' => $timeoutSeconds * 1000,
            'settleMs' => max(0, (int) config('rendered_capture.settle_milliseconds', 1500)),
            'maxTextChars' => max(1, (int) config('rendered_capture.max_text_chars', 50000)),
            'maxHtmlChars' => max(1, (int) config('rendered_capture.max_html_chars', 100000)),
            'userAgent' => (string) config('rendered_capture.user_agent', ''),
            'viewport' => [
                'width' => max(320, (int) config('rendered_capture.viewport_width', 1366)),
                'height' => max(240, (int) config('rendered_capture.viewport_height', 900)),
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return $this->fail($endpoint, 'render_timeout');
        }

        $output = trim($process->getOutput());
        $payload = json_decode($output, true);

        if (! is_array($payload)) {
            return $this->fail($endpoint, $this->truncateError(trim($process->getErrorOutput()) ?: 'invalid_render_output'));
        }

        if (! $process->isSuccessful() || ($payload['ok'] ?? false) !== true) {
            return $this->fail($endpoint, $this->truncateError((string) ($payload['error'] ?? $process->getErrorOutput() ?: 'render_failed')));
        }

        $endpoint->update([
            'rendered_capture_status' => 'completed',
            'rendered_page_title' => $this->nullableString($payload['title'] ?? null),
            'rendered_page_text' => $this->nullableString($payload['text'] ?? null),
            'rendered_page_content' => $this->nullableString($payload['html'] ?? null),
            'rendered_capture_duration_ms' => is_numeric($payload['durationMs'] ?? null) ? (int) $payload['durationMs'] : null,
            'rendered_capture_error' => null,
            'rendered_captured_at' => now(),
        ]);

        return new RenderedPageCaptureResult(true, 'completed');
    }

    private function fail(Endpoint $endpoint, string $error): RenderedPageCaptureResult
    {
        $endpoint->update([
            'rendered_capture_status' => 'failed',
            'rendered_capture_error' => $this->truncateError($error),
            'rendered_captured_at' => now(),
        ]);

        return new RenderedPageCaptureResult(false, 'failed', $this->truncateError($error));
    }

    private function truncateError(string $error): string
    {
        $error = trim($error);

        return mb_substr($error !== '' ? $error : 'render_failed', 0, 1024, 'UTF-8');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}

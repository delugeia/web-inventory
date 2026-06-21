<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Services\PageAnalysisInputBuilder;
use Tests\TestCase;

class PageAnalysisInputBuilderTest extends TestCase
{
    public function test_successful_html_page_produces_readable_text_metadata_hash_and_character_count(): void
    {
        $endpoint = $this->endpoint([
            'name' => 'Example',
            'resolved_url' => 'https://example.com/',
            'page_title' => 'Example Domain',
            'page_content' => <<<'HTML'
                <html>
                    <head>
                        <title>Example Domain</title>
                        <meta name="description" content="Example description for the domain.">
                    </head>
                    <body><main><h1>Real organization website</h1><p>Useful public content.</p></main></body>
                </html>
                HTML,
            'last_status_code' => 200,
            'content_type' => 'text/html; charset=UTF-8',
            'platform_headers' => ['server' => 'nginx'],
            'security_headers' => ['x-frame-options' => ['present' => true, 'value' => 'SAMEORIGIN']],
            'dns_summary' => ['a_count' => 1, 'aaaa_count' => 0, 'a_records' => ['203.0.113.10'], 'cname' => null],
            'canonical_url_check' => ['preferred_url' => 'https://example.com/', 'status' => 'pass', 'variants' => []],
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertTrue($input->shouldAnalyze);
        $this->assertNull($input->skipReason);
        $this->assertSame('Evaluate the final resolved webpage content and return a concise structured page status.', $input->payload['task']);
        $this->assertArrayHasKey('allowed_statuses', $input->payload);
        $this->assertSame('A public, substantive webpage for a real site, organization, service, business, project, publication, or application.', $input->payload['allowed_statuses']['NORMAL']);
        $this->assertSame('json', $input->payload['required_response']['format']);
        $this->assertSame('Example Domain', $input->payload['page_evidence']['title']);
        $this->assertSame('original_cached', $input->payload['page_evidence']['source']);
        $this->assertStringContainsString('Example description for the domain.', $input->payload['page_evidence']['text']);
        $this->assertStringContainsString('Real organization website', $input->payload['page_evidence']['text']);
        $this->assertArrayNotHasKey('redirects', $input->payload);
        $this->assertArrayNotHasKey('dns', $input->payload);
        $this->assertArrayNotHasKey('headers', $input->payload);
        $this->assertSame(hash('sha256', "page-analysis:v1\n".$input->inputText), $input->inputHash);
        $this->assertSame(mb_strlen($input->inputText, 'UTF-8'), $input->inputCharacterCount);
    }

    public function test_parked_placeholder_phrases_are_preserved(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://parked.example/',
            'page_title' => 'Domain Parked',
            'page_content' => '<html><body><h1>This domain is parked</h1><p>Buy this domain today or contact the owner.</p></body></html>',
            'last_status_code' => 200,
            'content_type' => 'text/html',
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertStringContainsString('This domain is parked', $input->payload['page_evidence']['text']);
        $this->assertStringContainsString('Buy this domain today', $input->inputText);
    }

    public function test_missing_cached_content_still_produces_metadata_only_input(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/file.pdf',
            'last_status_code' => 200,
            'content_type' => 'application/pdf',
            'page_content' => null,
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertTrue($input->shouldAnalyze);
        $this->assertNull($input->payload['page_evidence']['text']);
        $this->assertNull($input->payload['page_evidence']['html_excerpt']);
        $this->assertStringContainsString('application/pdf', $input->inputText);
    }

    public function test_failed_dns_or_timeout_snapshot_is_analyzable(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => null,
            'last_status_code' => null,
            'failure_reason' => 'timeout:https://example.com/',
            'failure_category' => 'timeout',
            'dns_summary' => ['a_count' => 1, 'aaaa_count' => 0, 'a_records' => ['203.0.113.10'], 'cname' => null],
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertTrue($input->shouldAnalyze);
        $this->assertStringContainsString('timeout:https://example.com/', $input->inputText);
        $this->assertStringContainsString('"failure_category": "timeout"', $input->inputText);
    }

    public function test_never_resolved_endpoint_is_skipped(): void
    {
        $endpoint = new Endpoint(['location' => 'example.com']);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertFalse($input->shouldAnalyze);
        $this->assertSame('never_resolved', $input->skipReason);
        $this->assertSame('', $input->inputText);
    }

    public function test_missing_resolve_result_is_skipped(): void
    {
        $endpoint = new Endpoint([
            'location' => 'example.com',
            'last_checked_at' => now(),
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertFalse($input->shouldAnalyze);
        $this->assertSame('missing_resolve_result', $input->skipReason);
    }

    public function test_scripts_styles_comments_and_hidden_text_are_removed(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'content_type' => 'text/html',
            'page_content' => <<<'HTML'
                <html>
                    <head>
                        <title>Visible Title</title>
                        <style>.hidden { display: none; }</style>
                        <script>var tracking = "do not include";</script>
                    </head>
                    <body>
                        <!-- do not include this comment -->
                        <svg><text>Hidden SVG Brand</text></svg>
                        <p style="display:none">Hidden display text</p>
                        <p aria-hidden="true">Aria hidden text</p>
                        <p>Visible classification text</p>
                    </body>
                </html>
                HTML,
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertStringContainsString('Visible classification text', $input->payload['page_evidence']['text']);
        $this->assertStringNotContainsString('tracking', $input->payload['page_evidence']['text']);
        $this->assertStringNotContainsString('do not include this comment', $input->payload['page_evidence']['text']);
        $this->assertStringNotContainsString('Hidden SVG Brand', $input->payload['page_evidence']['text']);
        $this->assertStringNotContainsString('Hidden display text', $input->payload['page_evidence']['text']);
        $this->assertStringNotContainsString('Aria hidden text', $input->payload['page_evidence']['text']);
    }

    public function test_long_content_is_capped_by_configured_max_input_characters(): void
    {
        config(['services.openai.max_input_chars' => 1800]);

        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'content_type' => 'text/html',
            'page_content' => '<html><body>'.str_repeat('Long public page content. ', 500).'</body></html>',
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertLessThanOrEqual(1800, $input->inputCharacterCount);
        $this->assertStringContainsString('[truncated]', $input->inputText);
        $this->assertSame($input->inputCharacterCount, mb_strlen($input->inputText, 'UTF-8'));
    }

    public function test_identical_snapshots_produce_identical_hashes(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'content_type' => 'text/html',
            'page_content' => '<html><body>Stable content.</body></html>',
        ]);

        $builder = new PageAnalysisInputBuilder;

        $first = $builder->build($endpoint);
        $second = $builder->build($endpoint);

        $this->assertSame($first->inputText, $second->inputText);
        $this->assertSame($first->inputHash, $second->inputHash);
    }

    public function test_changed_content_or_directive_version_changes_hash(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'content_type' => 'text/html',
            'page_content' => '<html><body>Original content.</body></html>',
        ]);

        $original = (new PageAnalysisInputBuilder)->build($endpoint);

        $endpoint->page_content = '<html><body>Updated content.</body></html>';
        $updatedContent = (new PageAnalysisInputBuilder)->build($endpoint);
        $updatedDirective = (new PageAnalysisInputBuilder('page-analysis:v2'))->build($endpoint);

        $this->assertNotSame($original->inputHash, $updatedContent->inputHash);
        $this->assertNotSame($updatedContent->inputHash, $updatedDirective->inputHash);
    }

    public function test_rendered_page_evidence_is_included_when_available(): void
    {
        $endpoint = $this->endpoint([
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'content_type' => 'text/html',
            'page_content' => '<html><script>document.body.innerText = "Under Construction";</script></html>',
            'rendered_capture_status' => 'completed',
            'rendered_page_title' => 'Rendered Page',
            'rendered_page_text' => 'This Page Is Under Construction Coming Soon Network Solutions',
            'rendered_page_content' => '<html><body>This Page Is Under Construction</body></html>',
            'rendered_capture_duration_ms' => 450,
            'rendered_captured_at' => now(),
        ]);

        $input = (new PageAnalysisInputBuilder)->build($endpoint);

        $this->assertSame('completed', $input->payload['rendered_capture']['status']);
        $this->assertSame('rendered', $input->payload['page_evidence']['source']);
        $this->assertSame('Rendered Page', $input->payload['page_evidence']['title']);
        $this->assertStringContainsString('Network Solutions', $input->payload['page_evidence']['text']);
        $this->assertStringContainsString('Under Construction', $input->inputText);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function endpoint(array $overrides = []): Endpoint
    {
        return new Endpoint(array_merge([
            'location' => 'example.com',
            'resolved_url' => 'https://example.com/',
            'last_status_code' => 200,
            'last_checked_at' => now(),
            'failure_reason' => null,
            'failure_category' => null,
            'redirect_followed' => false,
            'redirect_count' => 0,
            'redirect_chain' => null,
            'response_time_ms' => 125,
        ], $overrides));
    }
}

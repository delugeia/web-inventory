<?php

namespace App\Services;

use App\Models\Endpoint;
use DOMDocument;
use DOMXPath;

class PageAnalysisInputBuilder
{
    public function __construct(
        private readonly string $directiveVersion = 'page-analysis:v1',
    ) {}

    public function build(Endpoint $endpoint): PageAnalysisInput
    {
        if ($endpoint->last_checked_at === null) {
            return $this->skipped('never_resolved');
        }

        if ($endpoint->resolved_url === null && $endpoint->failure_reason === null) {
            return $this->skipped('missing_resolve_result');
        }

        $page = $this->pagePayload($endpoint);
        $payload = $this->payload($endpoint, $page);
        $inputText = $this->serializePayload($payload);
        $maxCharacters = $this->maxInputCharacters();

        if ($this->length($inputText) > $maxCharacters) {
            $payload = $this->truncatePageEvidence($payload, $maxCharacters);
            $inputText = $this->serializePayload($payload);
        }

        if ($this->length($inputText) > $maxCharacters) {
            $inputText = $this->truncate($inputText, $maxCharacters);
        }

        return new PageAnalysisInput(
            shouldAnalyze: true,
            skipReason: null,
            payload: $payload,
            inputText: $inputText,
            inputHash: hash('sha256', $this->directiveVersion."\n".$inputText),
            inputCharacterCount: $this->length($inputText),
        );
    }

    private function skipped(string $reason): PageAnalysisInput
    {
        return new PageAnalysisInput(
            shouldAnalyze: false,
            skipReason: $reason,
            payload: [],
            inputText: '',
            inputHash: '',
            inputCharacterCount: 0,
        );
    }

    /**
     * @param  array{readable_text: string|null, raw_html_excerpt: string|null}  $page
     * @return array<string, mixed>
     */
    private function payload(Endpoint $endpoint, array $page): array
    {
        $pageEvidence = $this->pageEvidencePayload($endpoint, $page);

        return [
            'directive_version' => $this->directiveVersion,
            'task' => 'Evaluate the final resolved webpage content and return a concise structured page status.',
            'instructions' => [
                'Use only the evidence in this payload. Do not assume facts that are not present.',
                'Classify the final resolved page content, not the redirect behavior.',
                'Return exactly one status from allowed_statuses. Do not invent additional statuses.',
                'Write a brief human-readable summary of what the resolved page appears to be.',
                'If multiple statuses seem possible, choose the most useful operational label for domain cleanup.',
                'Prefer PARKED over NORMAL when there are generic ad, registrar, under-construction, for-sale, suspended, or placeholder signals.',
                'Prefer RESTRICTED over NORMAL when login or access gating dominates the page.',
                'Prefer BROKEN when there is no meaningful page content to evaluate due to load, render, or network failure evidence.',
            ],
            'allowed_statuses' => [
                'NORMAL' => 'A public, substantive webpage for a real site, organization, service, business, project, publication, or application.',
                'RESTRICTED' => 'The page exists but primarily requires login, account access, authorization, SSO, payment, or another gate before meaningful content is available.',
                'PARKED' => 'A parked domain, registrar or hosting placeholder, under-construction page, domain-for-sale page, generic advertising or link page, suspended account page, default server page, or otherwise non-substantive placeholder.',
                'BROKEN' => 'The page could not be meaningfully evaluated because it failed to load, returned an error, timed out, had DNS, TLS, or connection failures, rendered blank, or contained only technical error output.',
                'OTHER' => 'The page has meaningful content or a notable state, but does not fit the other categories. Explain briefly in the summary.',
            ],
            'required_response' => [
                'format' => 'json',
                'schema' => [
                    'status' => 'string; exactly one of NORMAL, RESTRICTED, PARKED, BROKEN, OTHER',
                    'summary' => 'string; one brief sentence describing the resolved page',
                    'confidence' => 'integer; 0-100 confidence in the selected status',
                    'reasons' => 'array of 1-3 short strings citing the strongest evidence',
                ],
                'example' => [
                    'status' => 'PARKED',
                    'summary' => 'Looks like a parked page with generic marketing links.',
                    'confidence' => 92,
                    'reasons' => [
                        'Page says it is under construction',
                        'Contains generic related-search advertising links',
                    ],
                ],
            ],
            'endpoint' => [
                'location' => $endpoint->location,
                'resolved_url' => $endpoint->resolved_url,
            ],
            'fetch_result' => [
                'last_status_code' => $endpoint->last_status_code,
                'failure_reason' => $endpoint->failure_reason,
                'failure_category' => $endpoint->failure_category,
                'last_checked_at' => $endpoint->last_checked_at?->toIso8601String(),
                'content_type' => $endpoint->content_type,
            ],
            'rendered_capture' => [
                'status' => $endpoint->rendered_capture_status,
                'error' => $endpoint->rendered_capture_error,
                'captured_at' => $endpoint->rendered_captured_at?->toIso8601String(),
            ],
            'page_evidence' => $pageEvidence,
        ];
    }

    /**
     * @param  array{readable_text: string|null, raw_html_excerpt: string|null}  $page
     * @return array<string, mixed>
     */
    private function pageEvidencePayload(Endpoint $endpoint, array $page): array
    {
        $renderedText = $this->normalizeNullableText($endpoint->rendered_page_text);
        $renderedHtmlExcerpt = is_string($endpoint->rendered_page_content)
            ? $this->rawHtmlExcerpt($endpoint->rendered_page_content)
            : null;

        if ($renderedText !== null || $renderedHtmlExcerpt !== null || $endpoint->rendered_page_title !== null) {
            return [
                'source' => 'rendered',
                'title' => $endpoint->rendered_page_title ?: $endpoint->page_title,
                'text' => $renderedText,
                'html_excerpt' => $renderedHtmlExcerpt,
                'fallback_original_title' => $endpoint->page_title,
            ];
        }

        return [
            'source' => 'original_cached',
            'title' => $endpoint->page_title,
            'text' => $page['readable_text'],
            'html_excerpt' => $page['raw_html_excerpt'],
            'fallback_original_title' => null,
        ];
    }

    /**
     * @return array{readable_text: string|null, raw_html_excerpt: string|null}
     */
    private function pagePayload(Endpoint $endpoint): array
    {
        if (! is_string($endpoint->page_content) || trim($endpoint->page_content) === '') {
            return [
                'readable_text' => null,
                'raw_html_excerpt' => null,
            ];
        }

        $readableText = $this->readableTextFromHtml($endpoint->page_content);

        return [
            'readable_text' => $readableText !== '' ? $readableText : null,
            'raw_html_excerpt' => $readableText === '' ? $this->rawHtmlExcerpt($endpoint->page_content) : null,
        ];
    }

    private function readableTextFromHtml(string $html): string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

            if (! $loaded) {
                return $this->normalizeText(strip_tags($html));
            }

            $xpath = new DOMXPath($document);

            foreach ($xpath->query('//comment()') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            foreach ($xpath->query('//script|//style|//noscript|//template|//*[local-name()="svg"]|//*[@hidden]|//*[@aria-hidden="true"]|//*[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "display:none")]|//*[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "display: none")]|//*[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "visibility:hidden")]|//*[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "visibility: hidden")]') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            $parts = [];
            $title = $this->firstXPathText($xpath, '//title');
            $description = $this->firstXPathText($xpath, '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content');
            $body = $this->firstXPathText($xpath, '//body') ?: $this->normalizeText($document->textContent);

            foreach ([$title, $description, $body] as $part) {
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return $this->normalizeText(implode(' ', array_unique($parts)));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function firstXPathText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return $this->normalizeText((string) $nodes->item(0)?->textContent);
    }

    private function rawHtmlExcerpt(string $html): string
    {
        return $this->truncate($this->normalizeText($html), 2000);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function truncatePageEvidence(array $payload, int $maxCharacters): array
    {
        $payload['page_evidence']['text'] = $this->truncateEvidenceField(
            $payload,
            'text',
            max(0, $this->length((string) ($payload['page_evidence']['text'] ?? '')))
        );

        if ($this->length($this->serializePayload($payload)) <= $maxCharacters) {
            return $payload;
        }

        $payload['page_evidence']['html_excerpt'] = $this->truncateEvidenceField(
            $payload,
            'html_excerpt',
            max(0, $this->length((string) ($payload['page_evidence']['html_excerpt'] ?? '')))
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function truncateEvidenceField(array $payload, string $field, int $currentLength): ?string
    {
        $value = $payload['page_evidence'][$field] ?? null;
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $maxCharacters = $this->maxInputCharacters();
        $low = 0;
        $high = $currentLength;
        $best = '';

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $candidate = $this->truncate($value, $mid);
            $payload['page_evidence'][$field] = $candidate;

            if ($this->length($this->serializePayload($payload)) <= $maxCharacters) {
                $best = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best !== '' ? $best : '[truncated]';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function serializePayload(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeNullableText(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->normalizeText($value);
    }

    private function truncate(string $value, int $maxCharacters): string
    {
        if ($maxCharacters <= 0) {
            return '[truncated]';
        }

        if ($this->length($value) <= $maxCharacters) {
            return $value;
        }

        $marker = '[truncated]';
        $available = max(0, $maxCharacters - $this->length($marker) - 1);

        return rtrim($this->substring($value, 0, $available)).' '.$marker;
    }

    private function maxInputCharacters(): int
    {
        return max(1, (int) config('services.openai.max_input_chars', 50000));
    }

    private function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    private function substring(string $value, int $start, ?int $length = null): string
    {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
}

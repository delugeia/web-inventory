<?php

namespace App\Services;

use App\Models\Endpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class EndpointResolver
{
    private const PLATFORM_HEADERS = [
        'server',
        'x-powered-by',
        'via',
        'x-served-by',
        'cf-ray',
        'x-pantheon-styx-hostname',
        'x-cache',
        'x-drupal-cache',
    ];

    private const SECURITY_HEADERS = [
        'strict-transport-security',
        'content-security-policy',
        'x-frame-options',
        'x-content-type-options',
        'referrer-policy',
        'permissions-policy',
    ];

    /**
     * Resolve an endpoint by trying HTTP variants in priority order.
     *
     * @return array{
     *     resolved: bool,
     *     resolved_url: string|null,
     *     status_code: int|null,
     *     failure_reason: string|null,
     *     redirect_count: int,
     *     redirect_chain: array<int, array{url: string, status_code: int, location: string|null}>
     * }
     */
    public function resolve(Endpoint $endpoint): array
    {
        $target = $this->parseLocation((string) $endpoint->location);

        if (isset($target['error'])) {
            return $this->persistFailure($endpoint, $target['error'], [], 0);
        }

        /** @var string $host */
        $host = $target['host'];
        $dnsSummary = null;

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->persistFailure($endpoint, 'unsupported_host', [], 0);
        }

        if (! (bool) config('resolver.skip_dns_check', false)) {
            $dnsSummary = $this->dnsSummaryForHost($host);
            $hasDns = $this->dnsSummaryHasPresence($dnsSummary);

            if (! $hasDns && $target['mode'] === 'domain') {
                $hasDns = $this->dnsSummaryHasPresence($this->dnsSummaryForHost("www.{$host}"));
            }

            if (! $hasDns) {
                return $this->persistFailure($endpoint, 'dns_not_found', [], 0, [
                    'dns_summary' => $dnsSummary,
                ]);
            }
        }

        /** @var array<int, string> $urls */
        $urls = $target['urls'];

        if ($target['mode'] === 'domain') {
            return $this->resolveDomainEndpoint($endpoint, $host, $urls, $dnsSummary);
        }

        $lastReason = 'no_http_response';
        $lastRedirectChain = [];
        $lastRedirectCount = 0;
        $lastProbe = [];

        foreach ($urls as $url) {
            $probe = $this->probeWithRedirects($url);

            if ($probe['resolved']) {
                return $this->persistSuccess(
                    $endpoint,
                    $host,
                    $probe['resolved_url'],
                    $probe['status_code'],
                    $probe['redirect_chain'],
                    $probe['redirect_count'],
                    $dnsSummary,
                    $probe
                );
            }

            $lastReason = $probe['failure_reason'];
            $lastRedirectChain = $probe['redirect_chain'];
            $lastRedirectCount = $probe['redirect_count'];
            $lastProbe = $probe;
        }

        return $this->persistFailure($endpoint, $lastReason, $lastRedirectChain, $lastRedirectCount, [
            'dns_summary' => $dnsSummary,
            'response_time_ms' => $lastProbe['response_time_ms'] ?? null,
            'http_to_https_redirect' => $this->hasHttpToHttpsRedirect($lastRedirectChain),
        ]);
    }

    /**
     * @param array<int, string> $urls
     * @param array{a_count: int, aaaa_count: int, cname: string|null, a_records?: array<int, string>, aaaa_records?: array<int, string>}|null $dnsSummary
     * @return array<string, mixed>
     */
    private function resolveDomainEndpoint(Endpoint $endpoint, string $host, array $urls, ?array $dnsSummary): array
    {
        $probes = [];

        foreach ($urls as $url) {
            $probes[$url] = $this->probeWithRedirects($url);
        }

        $canonicalUrl = "https://{$host}/";
        $canonicalCheck = $this->canonicalUrlCheck($host, $canonicalUrl, $probes);
        $winningProbe = $this->winningDomainProbe($canonicalUrl, $probes);

        if ($winningProbe !== null) {
            return $this->persistSuccess(
                $endpoint,
                $host,
                $winningProbe['resolved_url'],
                $winningProbe['status_code'],
                $winningProbe['redirect_chain'],
                $winningProbe['redirect_count'],
                $dnsSummary,
                $winningProbe,
                $canonicalCheck
            );
        }

        $lastProbe = end($probes) ?: [];
        $lastReason = is_array($lastProbe) ? ($lastProbe['failure_reason'] ?? 'no_http_response') : 'no_http_response';
        $lastRedirectChain = is_array($lastProbe) ? ($lastProbe['redirect_chain'] ?? []) : [];
        $lastRedirectCount = is_array($lastProbe) ? ($lastProbe['redirect_count'] ?? 0) : 0;

        return $this->persistFailure($endpoint, $lastReason, $lastRedirectChain, $lastRedirectCount, [
            'dns_summary' => $dnsSummary,
            'response_time_ms' => is_array($lastProbe) ? ($lastProbe['response_time_ms'] ?? null) : null,
            'http_to_https_redirect' => $this->hasHttpToHttpsRedirect($lastRedirectChain),
            'canonical_url_check' => $canonicalCheck,
        ]);
    }

    /**
     * @return array{
     *     resolved: bool,
     *     resolved_url: string|null,
     *     status_code: int|null,
     *     failure_reason: string|null,
     *     redirect_count: int,
     *     redirect_chain: array<int, array{url: string, status_code: int, location: string|null}>
     * }
     */
    private function probeWithRedirects(string $initialUrl): array
    {
        $startedAt = hrtime(true);
        $maxHops = max(0, (int) config('resolver.redirect_max_hops', 5));
        $allowCrossHost = (bool) config('resolver.redirect_cross_host', true);

        $currentUrl = $initialUrl;
        $redirectCount = 0;
        $visited = [];
        $chain = [];

        while (true) {
            $visitedKey = $this->canonicalUrlForLoop($currentUrl);
            if (isset($visited[$visitedKey])) {
                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_loop',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            $visited[$visitedKey] = true;

            try {
                $response = Http::connectTimeout((int) config('resolver.connect_timeout', 3))
                    ->timeout((int) config('resolver.request_timeout', 6))
                    ->withUserAgent((string) config('resolver.user_agent', 'web-inventory-resolver/1.0'))
                    ->withOptions([
                        'allow_redirects' => false,
                        'http_errors' => false,
                    ])
                    ->get($currentUrl);
            } catch (ConnectionException $e) {
                $message = strtolower($e->getMessage());
                $isTimeout = str_contains($message, 'timed out') || str_contains($message, 'timeout');

                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => $isTimeout ? "timeout:{$currentUrl}" : "connection_failed:{$currentUrl}",
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            } catch (\Throwable) {
                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => "request_failed:{$currentUrl}",
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            $status = $response->status();
            if (! is_int($status) || $status < 100 || $status > 599) {
                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'invalid_http_status',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            if (! $this->isRedirectStatus($status)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => null,
                ];

                return $this->withProbeMetadata([
                    'resolved' => true,
                    'resolved_url' => $currentUrl,
                    'status_code' => $status,
                    'failure_reason' => null,
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt, $response);
            }

            $location = trim((string) $response->header('Location', ''));
            if ($location === '') {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => null,
                ];

                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_missing_location',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);
            if ($nextUrl === null) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $location,
                ];

                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_invalid_location',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            if (! $this->hasSupportedHttpScheme($nextUrl)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $nextUrl,
                ];

                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_unsupported_scheme',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            if (! $allowCrossHost && ! $this->sameHost($currentUrl, $nextUrl)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $nextUrl,
                ];

                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_cross_host_blocked',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            $redirectCount++;
            $chain[] = [
                'url' => $currentUrl,
                'status_code' => $status,
                'location' => $nextUrl,
            ];

            if ($redirectCount > $maxHops) {
                return $this->withProbeMetadata([
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_max_hops_exceeded',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ], $startedAt);
            }

            $currentUrl = $nextUrl;
        }
    }

    /**
     * @return array{
     *     mode?: 'url'|'domain',
     *     host?: string,
     *     urls?: array<int, string>,
     *     error?: string
     * }
     */
    private function parseLocation(string $location): array
    {
        $location = trim($location);
        if ($location === '') {
            return ['error' => 'invalid_location'];
        }

        if ($this->hasUrlScheme($location)) {
            return $this->parseAbsoluteUrl($location);
        }

        $host = $this->normalizeDomainInput($location);
        if ($host === null || ! $this->isValidHost($host)) {
            return ['error' => 'invalid_hostname'];
        }

        $baseHost = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return [
            'mode' => 'domain',
            'host' => $baseHost,
            'urls' => [
                "http://{$baseHost}/",
                "http://www.{$baseHost}/",
                "https://{$baseHost}/",
                "https://www.{$baseHost}/",
            ],
        ];
    }

    private function hasUrlScheme(string $value): bool
    {
        return preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $value) === 1;
    }

    private function hasSupportedHttpScheme(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @return array{
     *     mode?: 'url',
     *     host?: string,
     *     urls?: array<int, string>,
     *     error?: string
     * }
     */
    private function parseAbsoluteUrl(string $location): array
    {
        $parts = parse_url($location);
        if (! is_array($parts)) {
            return ['error' => 'invalid_location'];
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return ['error' => 'unsupported_scheme'];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['error' => 'unsupported_credentials'];
        }

        $host = $this->normalizeHostName((string) ($parts['host'] ?? ''));
        if ($host === null || ! $this->isValidHost($host)) {
            return ['error' => 'invalid_hostname'];
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return [
            'mode' => 'url',
            'host' => $host,
            'urls' => ["{$scheme}://{$host}{$port}{$path}{$query}"],
        ];
    }

    private function normalizeDomainInput(string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        $location = preg_replace('#/.*$#', '', $location);
        $location = preg_replace('/:\d+$/', '', (string) $location);

        return $this->normalizeHostName((string) $location);
    }

    private function normalizeHostName(string $host): ?string
    {
        $host = trim(strtolower(rtrim($host, '.')));
        if ($host === '') {
            return null;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host);
            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return strtolower($host);
    }

    private function isValidHost(string $host): bool
    {
        if (! str_contains($host, '.')) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function hasDnsPresenceForDomain(string $host): bool
    {
        return $this->dnsSummaryHasPresence($this->dnsSummaryForHost($host))
            || $this->dnsSummaryHasPresence($this->dnsSummaryForHost("www.{$host}"));
    }

    private function hasDnsForHost(string $host): bool
    {
        return $this->dnsSummaryHasPresence($this->dnsSummaryForHost($host))
            || $this->hasRecords($host, DNS_NS)
            || $this->hasRecords($host, DNS_SOA);
    }

    private function hasRecords(string $host, int $type): bool
    {
        try {
            $records = @dns_get_record($host, $type);

            return is_array($records) && count($records) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function dnsSummaryForHost(string $host): array
    {
        $aRecords = $this->recordsForHost($host, DNS_A);
        $aaaaRecords = $this->recordsForHost($host, DNS_AAAA);
        $cnameRecords = $this->recordsForHost($host, DNS_CNAME);

        return [
            'a_count' => count($aRecords),
            'aaaa_count' => count($aaaaRecords),
            'a_records' => $this->recordValues($aRecords, 'ip'),
            'aaaa_records' => $this->recordValues($aaaaRecords, 'ipv6'),
            'cname' => $this->firstCnameTarget($cnameRecords),
        ];
    }

    private function dnsSummaryHasPresence(array $summary): bool
    {
        return $summary['a_count'] > 0
            || $summary['aaaa_count'] > 0
            || $summary['cname'] !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordsForHost(string $host, int $type): array
    {
        try {
            $records = @dns_get_record($host, $type);

            return is_array($records) ? $records : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, string>
     */
    private function recordValues(array $records, string $key): array
    {
        $values = [];

        foreach ($records as $record) {
            $value = $record[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function firstCnameTarget(array $records): ?string
    {
        foreach ($records as $record) {
            $target = $record['target'] ?? null;
            if (is_string($target) && trim($target) !== '') {
                return strtolower(rtrim(trim($target), '.'));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function withProbeMetadata(array $result, int $startedAt, ?object $response = null): array
    {
        $result['response_time_ms'] = $this->elapsedMilliseconds($startedAt);
        $result['content_type'] = $response !== null ? $this->normalizedHeader($response, 'Content-Type') : null;
        $result['platform_headers'] = $response !== null ? $this->selectedHeaders($response, self::PLATFORM_HEADERS) : null;
        $result['security_headers'] = $response !== null ? $this->securityHeaders($response) : null;

        return $result;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    private function normalizedHeader(object $response, string $header): ?string
    {
        if (! method_exists($response, 'header')) {
            return null;
        }

        $value = $response->header($header);
        if (is_array($value)) {
            $value = implode(', ', array_filter($value, fn ($item) => is_string($item) && trim($item) !== ''));
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value !== '' ? mb_substr($value, 0, 512) : null;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, string>|null
     */
    private function selectedHeaders(object $response, array $headers): ?array
    {
        $selected = [];

        foreach ($headers as $header) {
            $value = $this->normalizedHeader($response, $header);
            if ($value !== null) {
                $selected[strtolower($header)] = $value;
            }
        }

        return count($selected) > 0 ? $selected : null;
    }

    /**
     * @return array<string, array{present: bool, value: string|null}>|null
     */
    private function securityHeaders(object $response): ?array
    {
        $headers = [];

        foreach (self::SECURITY_HEADERS as $header) {
            $value = $this->normalizedHeader($response, $header);
            $headers[$header] = [
                'present' => $value !== null,
                'value' => $value,
            ];
        }

        return $headers;
    }

    /**
     * @param array<string, array<string, mixed>> $probes
     * @return array<string, mixed>
     */
    private function canonicalUrlCheck(string $host, string $canonicalUrl, array $probes): array
    {
        $variants = [];

        foreach ($probes as $url => $probe) {
            $variants[] = $this->canonicalVariant($url, $canonicalUrl, $probe);
        }

        $results = array_column($variants, 'result');
        $status = in_array('failed', $results, true) || in_array('stays_http', $results, true) || in_array('external_redirect', $results, true)
            ? 'fail'
            : (count(array_filter($results, fn ($result) => $result !== 'canonical' && $result !== 'to_canonical' && $result !== 'forces_https')) > 0 ? 'warning' : 'pass');

        return [
            'preferred_url' => $canonicalUrl,
            'preferred_host' => $host,
            'preference' => 'https_no_www',
            'status' => $status,
            'variants' => $variants,
        ];
    }

    /**
     * @param array<string, mixed> $probe
     * @return array<string, mixed>
     */
    private function canonicalVariant(string $url, string $canonicalUrl, array $probe): array
    {
        $resolved = (bool) ($probe['resolved'] ?? false);
        $finalUrl = $resolved ? (string) ($probe['resolved_url'] ?? '') : null;
        $redirectChain = is_array($probe['redirect_chain'] ?? null) ? $probe['redirect_chain'] : [];
        $result = 'failed';
        $issue = $probe['failure_reason'] ?? null;

        if ($resolved && $finalUrl !== null) {
            if (! $this->isSuccessfulFinalStatus($probe['status_code'] ?? null)) {
                return [
                    'url' => $url,
                    'reachable' => false,
                    'final_url' => $finalUrl,
                    'status_code' => $probe['status_code'] ?? null,
                    'redirect_count' => $probe['redirect_count'] ?? 0,
                    'redirect_chain' => $redirectChain,
                    'result' => 'failed',
                    'issue' => 'Final response returned an unsuccessful status.',
                ];
            }

            $normalizedFinal = $this->canonicalUrlForLoop($finalUrl);
            $normalizedCanonical = $this->canonicalUrlForLoop($canonicalUrl);
            $finalHost = $this->hostFromUrl($finalUrl);
            $canonicalHost = $this->hostFromUrl($canonicalUrl);
            $startsHttp = $this->schemeFromUrl($url) === 'http';

            if ($finalHost !== null && $canonicalHost !== null && $this->baseHost($finalHost) !== $this->baseHost($canonicalHost)) {
                $result = 'external_redirect';
                $issue = 'Redirects away from the endpoint domain.';
            } elseif ($startsHttp && $this->schemeFromUrl($finalUrl) !== 'https') {
                $result = 'stays_http';
                $issue = 'Does not force HTTPS.';
            } elseif ($normalizedFinal === $normalizedCanonical && $startsHttp) {
                $result = 'forces_https';
                $issue = null;
            } elseif ($normalizedFinal === $normalizedCanonical) {
                $result = $this->canonicalUrlForLoop($url) === $normalizedCanonical ? 'canonical' : 'to_canonical';
                $issue = null;
            } elseif ($finalHost !== null && str_starts_with($finalHost, 'www.')) {
                $result = 'keeps_www';
                $issue = 'Resolves successfully, but keeps www.';
            } else {
                $result = 'different_https';
                $issue = 'Resolves to a different HTTPS URL.';
            }
        }

        return [
            'url' => $url,
            'reachable' => $resolved,
            'final_url' => $finalUrl,
            'status_code' => $probe['status_code'] ?? null,
            'redirect_count' => $probe['redirect_count'] ?? 0,
            'redirect_chain' => $redirectChain,
            'result' => $result,
            'issue' => $issue,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $probes
     * @return array<string, mixed>|null
     */
    private function winningDomainProbe(string $canonicalUrl, array $probes): ?array
    {
        $canonicalKey = $this->canonicalUrlForLoop($canonicalUrl);

        foreach ($probes as $probe) {
            if (($probe['resolved'] ?? false)
                && $this->isSuccessfulFinalStatus($probe['status_code'] ?? null)
                && $this->canonicalUrlForLoop((string) $probe['resolved_url']) === $canonicalKey) {
                return $probe;
            }
        }

        foreach ($probes as $probe) {
            if (($probe['resolved'] ?? false) && $this->isSuccessfulFinalStatus($probe['status_code'] ?? null)) {
                return $probe;
            }
        }

        return null;
    }

    private function isSuccessfulFinalStatus(mixed $statusCode): bool
    {
        return is_int($statusCode) && $statusCode >= 200 && $statusCode < 400;
    }

    /**
     * @return array{
     *     resolved: bool,
     *     resolved_url: string,
     *     status_code: int,
     *     failure_reason: null,
     *     redirect_count: int,
     *     redirect_chain: array<int, array{url: string, status_code: int, location: string|null}>
     * }
     */
    private function persistSuccess(
        Endpoint $endpoint,
        string $originalHost,
        string $resolvedUrl,
        int $statusCode,
        array $redirectChain,
        int $redirectCount,
        ?array $dnsSummary,
        array $probe,
        ?array $canonicalUrlCheck = null
    ): array {
        $resolvedHost = $this->hostFromUrl($resolvedUrl);
        $resolvedScheme = $this->schemeFromUrl($resolvedUrl);
        $hostChanged = $resolvedHost !== null && $originalHost !== $resolvedHost;
        $baseHostChanged = $resolvedHost !== null && $this->baseHost($originalHost) !== $this->baseHost($resolvedHost);
        $httpToHttpsRedirect = $this->hasHttpToHttpsRedirect($redirectChain);

        $endpoint->update([
            'resolved_url' => $resolvedUrl,
            'resolved_host' => $resolvedHost,
            'resolved_scheme' => $resolvedScheme,
            'host_changed' => $hostChanged,
            'base_host_changed' => $baseHostChanged,
            'http_to_https_redirect' => $httpToHttpsRedirect,
            'content_type' => $probe['content_type'] ?? null,
            'response_time_ms' => $probe['response_time_ms'] ?? null,
            'dns_summary' => $dnsSummary,
            'platform_headers' => $probe['platform_headers'] ?? null,
            'security_headers' => $probe['security_headers'] ?? null,
            'canonical_url_check' => $canonicalUrlCheck,
            'last_status_code' => $statusCode,
            'last_checked_at' => now(),
            'failure_reason' => null,
            'failure_category' => null,
            'redirect_followed' => $redirectCount > 0,
            'redirect_count' => $redirectCount,
            'redirect_chain' => count($redirectChain) > 0 ? $redirectChain : null,
        ]);

        return [
            'resolved' => true,
            'resolved_url' => $resolvedUrl,
            'resolved_host' => $resolvedHost,
            'resolved_scheme' => $resolvedScheme,
            'host_changed' => $hostChanged,
            'base_host_changed' => $baseHostChanged,
            'http_to_https_redirect' => $httpToHttpsRedirect,
            'content_type' => $probe['content_type'] ?? null,
            'response_time_ms' => $probe['response_time_ms'] ?? null,
            'dns_summary' => $dnsSummary,
            'platform_headers' => $probe['platform_headers'] ?? null,
            'security_headers' => $probe['security_headers'] ?? null,
            'canonical_url_check' => $canonicalUrlCheck,
            'status_code' => $statusCode,
            'failure_reason' => null,
            'failure_category' => null,
            'redirect_count' => $redirectCount,
            'redirect_chain' => $redirectChain,
        ];
    }

    /**
     * @param array<int, array{url: string, status_code: int, location: string|null}> $redirectChain
     * @return array{
     *     resolved: bool,
     *     resolved_url: null,
     *     status_code: null,
     *     failure_reason: string,
     *     redirect_count: int,
     *     redirect_chain: array<int, array{url: string, status_code: int, location: string|null}>
     * }
     */
    private function persistFailure(Endpoint $endpoint, string $reason, array $redirectChain, int $redirectCount, array $metadata = []): array
    {
        $failureCategory = $this->failureCategory($reason);
        $httpToHttpsRedirect = array_key_exists('http_to_https_redirect', $metadata)
            ? $metadata['http_to_https_redirect']
            : (count($redirectChain) > 0 ? $this->hasHttpToHttpsRedirect($redirectChain) : null);

        $endpoint->update([
            'resolved_url' => null,
            'resolved_host' => null,
            'resolved_scheme' => null,
            'host_changed' => null,
            'base_host_changed' => null,
            'http_to_https_redirect' => $httpToHttpsRedirect,
            'content_type' => null,
            'response_time_ms' => $metadata['response_time_ms'] ?? null,
            'dns_summary' => $metadata['dns_summary'] ?? null,
            'platform_headers' => null,
            'security_headers' => null,
            'canonical_url_check' => $metadata['canonical_url_check'] ?? null,
            'last_status_code' => null,
            'last_checked_at' => now(),
            'failure_reason' => $reason,
            'failure_category' => $failureCategory,
            'redirect_followed' => $redirectCount > 0,
            'redirect_count' => $redirectCount,
            'redirect_chain' => count($redirectChain) > 0 ? $redirectChain : null,
        ]);

        return [
            'resolved' => false,
            'resolved_url' => null,
            'resolved_host' => null,
            'resolved_scheme' => null,
            'host_changed' => null,
            'base_host_changed' => null,
            'http_to_https_redirect' => $httpToHttpsRedirect,
            'content_type' => null,
            'response_time_ms' => $metadata['response_time_ms'] ?? null,
            'dns_summary' => $metadata['dns_summary'] ?? null,
            'platform_headers' => null,
            'security_headers' => null,
            'canonical_url_check' => $metadata['canonical_url_check'] ?? null,
            'status_code' => null,
            'failure_reason' => $reason,
            'failure_category' => $failureCategory,
            'redirect_count' => $redirectCount,
            'redirect_chain' => $redirectChain,
        ];
    }

    private function isRedirectStatus(int $status): bool
    {
        return $status >= 300 && $status <= 399;
    }

    private function sameHost(string $fromUrl, string $toUrl): bool
    {
        $fromHost = strtolower((string) parse_url($fromUrl, PHP_URL_HOST));
        $toHost = strtolower((string) parse_url($toUrl, PHP_URL_HOST));

        return $fromHost !== '' && $fromHost === $toHost;
    }

    private function hostFromUrl(string $url): ?string
    {
        $host = strtolower(trim(rtrim((string) parse_url($url, PHP_URL_HOST), '.')));

        return $host !== '' ? $host : null;
    }

    private function schemeFromUrl(string $url): ?string
    {
        $scheme = strtolower(trim((string) parse_url($url, PHP_URL_SCHEME)));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : null;
    }

    private function baseHost(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * @param array<int, array{url: string, status_code: int, location: string|null}> $redirectChain
     */
    private function hasHttpToHttpsRedirect(array $redirectChain): bool
    {
        foreach ($redirectChain as $hop) {
            $url = (string) ($hop['url'] ?? '');
            $location = (string) ($hop['location'] ?? '');

            if ($url !== ''
                && $location !== ''
                && $this->schemeFromUrl($url) === 'http'
                && $this->schemeFromUrl($location) === 'https') {
                return true;
            }
        }

        return false;
    }

    private function failureCategory(string $reason): string
    {
        return match (true) {
            str_starts_with($reason, 'dns_') => 'dns',
            str_starts_with($reason, 'timeout') => 'timeout',
            str_starts_with($reason, 'connection_failed') => 'connection',
            str_contains($reason, 'tls') || str_contains($reason, 'ssl') => 'tls',
            str_starts_with($reason, 'redirect_') => 'redirect',
            str_contains($reason, 'http_status') || $reason === 'invalid_http_status' => 'http_status',
            str_starts_with($reason, 'unsupported_') => 'unsupported',
            str_contains($reason, 'invalid') => 'invalid',
            str_contains($reason, 'request') => 'request',
            default => 'request',
        };
    }

    private function canonicalUrlForLoop(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if ($this->hasUrlScheme($location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $baseAuthority = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($location, '//')) {
            return $base['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $baseAuthority.$location;
        }

        $basePath = isset($base['path']) && $base['path'] !== '' ? $base['path'] : '/';

        if (str_starts_with($location, '?')) {
            return $baseAuthority.$basePath.$location;
        }

        if (str_starts_with($location, '#')) {
            $baseQuery = isset($base['query']) ? '?'.$base['query'] : '';

            return $baseAuthority.$basePath.$baseQuery.$location;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $basePath);
        $mergedPath = ($directory !== null ? $directory : '/').$location;
        $normalizedPath = $this->normalizePath($mergedPath);

        return $baseAuthority.$normalizedPath;
    }

    private function normalizePath(string $path): string
    {
        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            $normalized[] = $segment;
        }

        $result = '/'.implode('/', $normalized);
        if ($path !== '/' && str_ends_with($path, '/') && ! str_ends_with($result, '/')) {
            $result .= '/';
        }

        return $result === '' ? '/' : $result;
    }
}

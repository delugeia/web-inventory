<?php

namespace App\Services;

use App\Models\Endpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class EndpointResolver
{
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

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->persistFailure($endpoint, 'unsupported_host', [], 0);
        }

        if (! (bool) config('resolver.skip_dns_check', false)) {
            $hasDns = $target['mode'] === 'url'
                ? $this->hasDnsForHost($host)
                : $this->hasDnsPresenceForDomain($host);
            if (! $hasDns) {
                return $this->persistFailure($endpoint, 'dns_not_found', [], 0);
            }
        }

        /** @var array<int, string> $urls */
        $urls = $target['urls'];

        $lastReason = 'no_http_response';
        $lastRedirectChain = [];
        $lastRedirectCount = 0;

        foreach ($urls as $url) {
            $probe = $this->probeWithRedirects($url);

            if ($probe['resolved']) {
                return $this->persistSuccess(
                    $endpoint,
                    $probe['resolved_url'],
                    $probe['status_code'],
                    $probe['redirect_chain'],
                    $probe['redirect_count']
                );
            }

            $lastReason = $probe['failure_reason'];
            $lastRedirectChain = $probe['redirect_chain'];
            $lastRedirectCount = $probe['redirect_count'];
        }

        return $this->persistFailure($endpoint, $lastReason, $lastRedirectChain, $lastRedirectCount);
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
        $maxHops = max(0, (int) config('resolver.redirect_max_hops', 5));
        $allowCrossHost = (bool) config('resolver.redirect_cross_host', true);

        $currentUrl = $initialUrl;
        $redirectCount = 0;
        $visited = [];
        $chain = [];

        while (true) {
            $visitedKey = $this->canonicalUrlForLoop($currentUrl);
            if (isset($visited[$visitedKey])) {
                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_loop',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
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

                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => $isTimeout ? "timeout:{$currentUrl}" : "connection_failed:{$currentUrl}",
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            } catch (\Throwable) {
                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => "request_failed:{$currentUrl}",
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            $status = $response->status();
            if (! is_int($status) || $status < 100 || $status > 599) {
                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'invalid_http_status',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            if (! $this->isRedirectStatus($status)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => null,
                ];

                return [
                    'resolved' => true,
                    'resolved_url' => $currentUrl,
                    'status_code' => $status,
                    'failure_reason' => null,
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            $location = trim((string) $response->header('Location', ''));
            if ($location === '') {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => null,
                ];

                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_missing_location',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);
            if ($nextUrl === null) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $location,
                ];

                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_invalid_location',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            if (! $this->hasSupportedHttpScheme($nextUrl)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $nextUrl,
                ];

                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_unsupported_scheme',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            if (! $allowCrossHost && ! $this->sameHost($currentUrl, $nextUrl)) {
                $chain[] = [
                    'url' => $currentUrl,
                    'status_code' => $status,
                    'location' => $nextUrl,
                ];

                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_cross_host_blocked',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
            }

            $redirectCount++;
            $chain[] = [
                'url' => $currentUrl,
                'status_code' => $status,
                'location' => $nextUrl,
            ];

            if ($redirectCount > $maxHops) {
                return [
                    'resolved' => false,
                    'resolved_url' => null,
                    'status_code' => null,
                    'failure_reason' => 'redirect_max_hops_exceeded',
                    'redirect_count' => $redirectCount,
                    'redirect_chain' => $chain,
                ];
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
                "https://{$baseHost}/",
                "https://www.{$baseHost}/",
                "http://{$baseHost}/",
                "http://www.{$baseHost}/",
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
        return $this->hasDnsForHost($host) || $this->hasDnsForHost("www.{$host}");
    }

    private function hasDnsForHost(string $host): bool
    {
        return $this->hasRecords($host, DNS_NS)
            || $this->hasRecords($host, DNS_SOA)
            || $this->hasRecords($host, DNS_A)
            || $this->hasRecords($host, DNS_AAAA)
            || $this->hasRecords($host, DNS_CNAME);
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
        string $resolvedUrl,
        int $statusCode,
        array $redirectChain,
        int $redirectCount
    ): array {
        $endpoint->update([
            'resolved_url' => $resolvedUrl,
            'last_status_code' => $statusCode,
            'last_checked_at' => now(),
            'failure_reason' => null,
            'redirect_followed' => $redirectCount > 0,
            'redirect_count' => $redirectCount,
            'redirect_chain' => count($redirectChain) > 0 ? $redirectChain : null,
        ]);

        return [
            'resolved' => true,
            'resolved_url' => $resolvedUrl,
            'status_code' => $statusCode,
            'failure_reason' => null,
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
    private function persistFailure(Endpoint $endpoint, string $reason, array $redirectChain, int $redirectCount): array
    {
        $endpoint->update([
            'resolved_url' => null,
            'last_status_code' => null,
            'last_checked_at' => now(),
            'failure_reason' => $reason,
            'redirect_followed' => $redirectCount > 0,
            'redirect_count' => $redirectCount,
            'redirect_chain' => count($redirectChain) > 0 ? $redirectChain : null,
        ]);

        return [
            'resolved' => false,
            'resolved_url' => null,
            'status_code' => null,
            'failure_reason' => $reason,
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

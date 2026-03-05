<?php

namespace App\Support;

class EndpointLocationNormalizer
{
    public static function normalize(string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }

        $url = self::normalizeAbsoluteHttpUrl($location);
        if ($url !== null) {
            return $url;
        }

        if (self::looksLikeDomain($location)) {
            return strtolower($location);
        }

        return $location;
    }

    private static function normalizeAbsoluteHttpUrl(string $location): ?string
    {
        if (preg_match('/^https?:\/\//i', $location) !== 1) {
            return null;
        }

        $parts = parse_url($location);
        if (! is_array($parts)) {
            return null;
        }

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $user = isset($parts['user']) ? (string) $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':'.(string) $parts['pass'] : '';
        $auth = $user !== '' ? "{$user}{$pass}@" : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = array_key_exists('query', $parts) ? '?'.$parts['query'] : '';
        $fragment = array_key_exists('fragment', $parts) ? '#'.$parts['fragment'] : '';

        return "{$scheme}://{$auth}{$host}{$port}{$path}{$query}{$fragment}";
    }

    private static function looksLikeDomain(string $location): bool
    {
        if (! str_contains($location, '.')) {
            return false;
        }

        return filter_var(
            rtrim($location, '.'),
            FILTER_VALIDATE_DOMAIN,
            FILTER_FLAG_HOSTNAME
        ) !== false;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->string('resolved_host')->nullable()->after('resolved_url');
            $table->string('resolved_scheme', 16)->nullable()->after('resolved_host');
            $table->boolean('host_changed')->nullable()->after('resolved_scheme');
            $table->boolean('base_host_changed')->nullable()->after('host_changed');
            $table->boolean('http_to_https_redirect')->nullable()->after('base_host_changed');
            $table->string('content_type', 512)->nullable()->after('http_to_https_redirect');
            $table->unsignedInteger('response_time_ms')->nullable()->after('content_type');
            $table->json('dns_summary')->nullable()->after('response_time_ms');
            $table->json('platform_headers')->nullable()->after('dns_summary');
            $table->json('security_headers')->nullable()->after('platform_headers');
            $table->string('failure_category', 64)->nullable()->after('failure_reason');
        });

        Schema::table('endpoint_resolution_run_items', function (Blueprint $table) {
            $table->string('resolved_host')->nullable()->after('resolved_url');
            $table->string('resolved_scheme', 16)->nullable()->after('resolved_host');
            $table->boolean('host_changed')->nullable()->after('resolved_scheme');
            $table->boolean('base_host_changed')->nullable()->after('host_changed');
            $table->boolean('http_to_https_redirect')->nullable()->after('base_host_changed');
            $table->string('content_type', 512)->nullable()->after('http_to_https_redirect');
            $table->unsignedInteger('response_time_ms')->nullable()->after('content_type');
            $table->json('dns_summary')->nullable()->after('response_time_ms');
            $table->json('platform_headers')->nullable()->after('dns_summary');
            $table->json('security_headers')->nullable()->after('platform_headers');
            $table->string('failure_category', 64)->nullable()->after('failure_reason');
        });

        $this->backfillEndpointDetails();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoint_resolution_run_items', function (Blueprint $table) {
            $table->dropColumn([
                'resolved_host',
                'resolved_scheme',
                'host_changed',
                'base_host_changed',
                'http_to_https_redirect',
                'content_type',
                'response_time_ms',
                'dns_summary',
                'platform_headers',
                'security_headers',
                'failure_category',
            ]);
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'resolved_host',
                'resolved_scheme',
                'host_changed',
                'base_host_changed',
                'http_to_https_redirect',
                'content_type',
                'response_time_ms',
                'dns_summary',
                'platform_headers',
                'security_headers',
                'failure_category',
            ]);
        });
    }

    private function backfillEndpointDetails(): void
    {
        DB::table('endpoints')
            ->select(['id', 'location', 'resolved_url', 'redirect_chain', 'failure_reason'])
            ->orderBy('id')
            ->chunkById(100, function ($endpoints): void {
                foreach ($endpoints as $endpoint) {
                    $resolvedHost = $this->hostFromUrl($endpoint->resolved_url);
                    $resolvedScheme = $this->schemeFromUrl($endpoint->resolved_url);
                    $originalHost = $this->originalHostFromLocation((string) $endpoint->location);

                    DB::table('endpoints')
                        ->where('id', $endpoint->id)
                        ->update([
                            'resolved_host' => $resolvedHost,
                            'resolved_scheme' => $resolvedScheme,
                            'host_changed' => $originalHost !== null && $resolvedHost !== null
                                ? $originalHost !== $resolvedHost
                                : null,
                            'base_host_changed' => $originalHost !== null && $resolvedHost !== null
                                ? $this->baseHost($originalHost) !== $this->baseHost($resolvedHost)
                                : null,
                            'http_to_https_redirect' => $this->hasHttpToHttpsRedirect($endpoint->redirect_chain),
                            'failure_category' => $this->failureCategory($endpoint->failure_reason),
                        ]);
                }
            });
    }

    private function originalHostFromLocation(string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $location) === 1) {
            return $this->normalizeHost((string) parse_url($location, PHP_URL_HOST));
        }

        $location = preg_replace('#/.*$#', '', $location);
        $location = preg_replace('/:\d+$/', '', (string) $location);
        $host = $this->normalizeHost((string) $location);

        return $host !== null && str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function hostFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return $this->normalizeHost((string) parse_url($url, PHP_URL_HOST));
    }

    private function schemeFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $scheme : null;
    }

    private function normalizeHost(string $host): ?string
    {
        $host = trim(strtolower(rtrim($host, '.')));

        return $host !== '' ? $host : null;
    }

    private function baseHost(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function hasHttpToHttpsRedirect(?string $redirectChain): ?bool
    {
        if ($redirectChain === null || trim($redirectChain) === '') {
            return null;
        }

        $chain = json_decode($redirectChain, true);
        if (! is_array($chain)) {
            return null;
        }

        foreach ($chain as $hop) {
            if (! is_array($hop) || empty($hop['url']) || empty($hop['location'])) {
                continue;
            }

            if ($this->schemeFromUrl((string) $hop['url']) === 'http'
                && $this->schemeFromUrl((string) $hop['location']) === 'https') {
                return true;
            }
        }

        return false;
    }

    private function failureCategory(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

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
};

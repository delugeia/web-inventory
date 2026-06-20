<?php

namespace App\Models;

use App\Support\EndpointLocationNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Endpoint extends Model
{
    protected $fillable = [
        'location',
        'name',
        'resolved_url',
        'page_title',
        'page_content',
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
        'canonical_url_check',
        'last_status_code',
        'last_checked_at',
        'failure_reason',
        'failure_category',
        'redirect_followed',
        'redirect_count',
        'redirect_chain',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'host_changed' => 'boolean',
            'base_host_changed' => 'boolean',
            'http_to_https_redirect' => 'boolean',
            'response_time_ms' => 'integer',
            'dns_summary' => 'array',
            'platform_headers' => 'array',
            'security_headers' => 'array',
            'canonical_url_check' => 'array',
            'redirect_followed' => 'boolean',
            'redirect_count' => 'integer',
            'redirect_chain' => 'array',
        ];
    }

    /**
     * Order endpoints by the next one that should be resolved.
     *
     * Never-checked endpoints come first, sorted alphabetically by location.
     * Checked endpoints follow, sorted by oldest check time.
     *
     * @param Builder<Endpoint> $query
     * @return Builder<Endpoint>
     */
    public function scopeNextToResolve(Builder $query): Builder
    {
        return $query
            ->orderByRaw('last_checked_at is not null')
            ->orderBy('last_checked_at')
            ->orderBy('location');
    }

    protected function location(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value)
                ? EndpointLocationNormalizer::normalize($value)
                : $value
        );
    }
}

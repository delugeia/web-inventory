<?php

namespace App\Models;

use App\Support\EndpointLocationNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Endpoint extends Model
{
    protected $fillable = [
        'location',
        'name',
        'managed_by',
        'dns_provider',
        'resolved_url',
        'page_title',
        'page_content',
        'rendered_capture_status',
        'rendered_page_title',
        'rendered_page_text',
        'rendered_page_content',
        'rendered_capture_duration_ms',
        'rendered_capture_error',
        'rendered_captured_at',
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
            'rendered_capture_duration_ms' => 'integer',
            'rendered_captured_at' => 'datetime',
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
     * @param  Builder<Endpoint>  $query
     * @return Builder<Endpoint>
     */
    public function scopeNextToResolve(Builder $query): Builder
    {
        return $query
            ->orderByRaw('last_checked_at is not null')
            ->orderBy('last_checked_at')
            ->orderBy('location');
    }

    public function pageAnalyses(): HasMany
    {
        return $this->hasMany(EndpointPageAnalysis::class);
    }

    public function latestPageAnalysis(): HasOne
    {
        return $this->hasOne(EndpointPageAnalysis::class)->latestOfMany();
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointResolutionRunItem extends Model
{
    protected $fillable = [
        'endpoint_resolution_run_id',
        'endpoint_id',
        'position',
        'location',
        'status',
        'resolved_url',
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
        'failure_reason',
        'failure_category',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_resolution_run_id' => 'integer',
            'endpoint_id' => 'integer',
            'position' => 'integer',
            'last_status_code' => 'integer',
            'host_changed' => 'boolean',
            'base_host_changed' => 'boolean',
            'http_to_https_redirect' => 'boolean',
            'response_time_ms' => 'integer',
            'dns_summary' => 'array',
            'platform_headers' => 'array',
            'security_headers' => 'array',
            'canonical_url_check' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(EndpointResolutionRun::class, 'endpoint_resolution_run_id');
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointPageAnalysis extends Model
{
    protected $fillable = [
        'endpoint_id',
        'endpoint_resolution_run_item_id',
        'status',
        'page_status',
        'confidence',
        'summary',
        'reasons',
        'signals',
        'model',
        'directive_version',
        'input_hash',
        'input_character_count',
        'response_time_ms',
        'failure_category',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_id' => 'integer',
            'endpoint_resolution_run_item_id' => 'integer',
            'confidence' => 'integer',
            'reasons' => 'array',
            'signals' => 'array',
            'input_character_count' => 'integer',
            'response_time_ms' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    public function resolutionRunItem(): BelongsTo
    {
        return $this->belongsTo(EndpointResolutionRunItem::class, 'endpoint_resolution_run_item_id');
    }
}

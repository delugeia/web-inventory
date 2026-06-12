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
        'last_status_code',
        'failure_reason',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_resolution_run_id' => 'integer',
            'endpoint_id' => 'integer',
            'position' => 'integer',
            'last_status_code' => 'integer',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EndpointResolutionRun extends Model
{
    protected $fillable = [
        'requested_count',
        'total_count',
        'resolved_count',
        'failed_count',
        'status',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_count' => 'integer',
            'total_count' => 'integer',
            'resolved_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EndpointResolutionRunItem::class)->orderBy('position');
    }
}

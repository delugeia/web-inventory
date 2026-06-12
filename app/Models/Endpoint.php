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
        'last_status_code',
        'last_checked_at',
        'failure_reason',
        'redirect_followed',
        'redirect_count',
        'redirect_chain',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
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

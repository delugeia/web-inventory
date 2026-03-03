<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endpoint extends Model
{
    protected $fillable = [
        'location',
        'name',
        'last_status_code',
        'last_checked_at',
    ];
}
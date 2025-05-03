<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteSegment extends Model
{
    protected $fillable = [
        'route_id',
        'sequence',
        'start_point_id',
        'end_point_id',
        'distance',
        'duration',
        'geometry',
        'encoded_polyline',
        'profile',
        'cache_key',
        'expires_at',
    ];

    protected $casts = [
        'distance' => 'float',
        'duration' => 'float',
        'expires_at' => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function startPoint(): BelongsTo
    {
        return $this->belongsTo(RoutePoint::class, 'start_point_id');
    }

    public function endPoint(): BelongsTo
    {
        return $this->belongsTo(RoutePoint::class, 'end_point_id');
    }
}

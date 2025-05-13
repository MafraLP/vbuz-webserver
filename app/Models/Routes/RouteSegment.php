<?php

namespace App\Models\Routes;

use App\Models\Users\Routes\Route;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Clickbar\Magellan\Data\Geometries\LineString;

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
        // Se você adicionar uma coluna de geometria LineString no futuro
        // 'linestring_geometry' => LineString::class,
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

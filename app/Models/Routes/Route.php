<?php

namespace App\Models\Routes;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'total_distance',
        'total_duration',
        'is_published',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_distance' => 'float',
        'total_duration' => 'float',
        'is_published' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(RouteSegment::class)->orderBy('sequence');
    }
}

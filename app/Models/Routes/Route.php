<?php

namespace App\Models\Routes;

use App\Models\Institution;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'name',
        'institution_id',
        'total_distance',
        'total_duration',
        'is_published',
        'last_calculated_at',
        'calculation_status',        // ← ADICIONAR
        'calculation_started_at',    // ← ADICIONAR
        'calculation_completed_at',  // ← ADICIONAR
        'calculation_error'          // ← ADICIONAR
    ];

    protected $casts = [
        'total_distance' => 'float',
        'total_duration' => 'float',
        'is_published' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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

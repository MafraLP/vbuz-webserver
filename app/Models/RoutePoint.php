<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePoint extends Model
{
    protected $fillable = [
        'route_id',
        'sequence',
        'name',
        'description',
        'latitude',
        'longitude',
        'location',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Criar o ponto PostGIS a partir da latitude e longitude
            $model->location = \DB::raw("ST_SetSRID(ST_MakePoint({$model->longitude}, {$model->latitude}), 4326)");
        });

        static::updating(function ($model) {
            if ($model->isDirty(['latitude', 'longitude'])) {
                $model->location = \DB::raw("ST_SetSRID(ST_MakePoint({$model->longitude}, {$model->latitude}), 4326)");
            }
        });
    }
}

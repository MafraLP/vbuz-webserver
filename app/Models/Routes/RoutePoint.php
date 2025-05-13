<?php

namespace App\Models\Routes;

use App\Models\Routes\Route;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;

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
        'location' => Point::class,
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->latitude && $model->longitude) {
                $model->location = Point::makeGeodetic($model->latitude, $model->longitude);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty(['latitude', 'longitude'])) {
                $model->location = Point::makeGeodetic($model->latitude, $model->longitude);
            }
        });
    }

    /**
     * Encontrar pontos próximos a este ponto
     *
     * @param float $distanceMeters Distância máxima em metros
     * @param int|null $limit Limite de resultados (null para sem limite)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findNearbyPoints(float $distanceMeters = 2000, ?int $limit = null)
    {
        $query = self::where('id', '!=', $this->id)
            ->select()
            ->addSelect(ST::distanceSphere($this->location, 'location')->as('distance'))
            ->where(ST::distanceSphere($this->location, 'location'), '<=', $distanceMeters)
            ->orderBy(ST::distanceSphere($this->location, 'location'));

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Encontrar pontos próximos a uma localização específica
     *
     * @param float $latitude Latitude do ponto central
     * @param float $longitude Longitude do ponto central
     * @param float $distanceMeters Distância máxima em metros
     * @param int|null $limit Limite de resultados (null para sem limite)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findNearby(float $latitude, float $longitude, float $distanceMeters = 2000, ?int $limit = null)
    {
        $currentPosition = Point::makeGeodetic($latitude, $longitude);

        $query = self::select()
            ->addSelect(ST::distanceSphere($currentPosition, 'location')->as('distance'))
            ->where(ST::distanceSphere($currentPosition, 'location'), '<=', $distanceMeters)
            ->orderBy(ST::distanceSphere($currentPosition, 'location'));

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}

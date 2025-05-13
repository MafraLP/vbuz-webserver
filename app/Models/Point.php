<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Clickbar\Magellan\Data\Geometries\Point as GeometryPoint;
use Clickbar\Magellan\Database\PostgisFunctions\ST;

class Point extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'latitude',
        'longitude',
        'institution_id',
        'is_active',
        'notes',
        'location',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'location' => GeometryPoint::class,
    ];

    /**
     * Inicializa o modelo
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if ($model->latitude && $model->longitude) {
                $model->location = GeometryPoint::makeGeodetic($model->latitude, $model->longitude);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty(['latitude', 'longitude'])) {
                $model->location = GeometryPoint::makeGeodetic($model->latitude, $model->longitude);
            }
        });
    }

    /**
     * Relacionamento com a instituição à qual este ponto pertence
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Obtém a distância entre este ponto e outro ponto (em metros)
     *
     * @param Point|array $point Outro ponto ou array com [latitude, longitude]
     * @return float
     */
    public function distanceTo($point): float
    {
        // Se for um array, criar um ponto geométrico
        if (is_array($point)) {
            $otherPoint = GeometryPoint::makeGeodetic($point['latitude'], $point['longitude']);
        } else if ($point instanceof Point) {
            $otherPoint = $point->location;
        } else {
            $otherPoint = $point; // Assume que já é um GeometryPoint
        }

        // Usar a função distanceSphere do Magellan para calcular a distância
        $result = $this->select()
            ->addSelect(ST::distanceSphere($this->location, $otherPoint)->as('distance'))
            ->first();

        return $result->distance;
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
        $query = Point::where('id', '!=', $this->id)
            ->where('is_active', true)
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
     * @param array|null $institutionIds IDs das instituições para filtrar (null para todas)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findNearby(float $latitude, float $longitude, float $distanceMeters = 2000, ?int $limit = null, ?array $institutionIds = null)
    {
        $currentPosition = GeometryPoint::makeGeodetic($latitude, $longitude);

        $query = self::where('is_active', true)
            ->select()
            ->addSelect(ST::distanceSphere($currentPosition, 'location')->as('distance'))
            ->where(ST::distanceSphere($currentPosition, 'location'), '<=', $distanceMeters)
            ->orderBy(ST::distanceSphere($currentPosition, 'location'));

        // Filtrar por instituições, se especificado
        if ($institutionIds) {
            $query->whereIn('institution_id', $institutionIds);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}

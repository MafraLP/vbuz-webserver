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
        'point_id',  // ⭐ CHAVE ESTRANGEIRA para points
        'sequence',
        'type',
        'stop_duration',
        'is_optional',
        'route_specific_notes',
        'arrival_time',
        'departure_time',
    ];

    protected $casts = [
        'route_id' => 'integer',
        'point_id' => 'integer',  // ⭐ ESSENCIAL
        'sequence' => 'integer',
        'stop_duration' => 'integer',
        'is_optional' => 'boolean',
        'arrival_time' => 'datetime:H:i',
        'departure_time' => 'datetime:H:i',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    protected static function booted()
    {
        static::creating(function ($routePoint) {
            // Validar que route_id e point_id não são null
            if (!$routePoint->route_id) {
                throw new \Exception('route_id é obrigatório no RoutePoint');
            }

            if (!$routePoint->point_id) {
                throw new \Exception('point_id é obrigatório no RoutePoint');
            }

            // Garantir que sequence não é null
            if ($routePoint->sequence === null) {
                $routePoint->sequence = 0;
            }

            // Garantir que type não é null
            if (!$routePoint->type) {
                $routePoint->type = 'intermediate';
            }
        });

        static::updating(function ($routePoint) {
            // Validações similares para update
            if (!$routePoint->route_id) {
                throw new \Exception('route_id é obrigatório no RoutePoint');
            }

            if (!$routePoint->point_id) {
                throw new \Exception('point_id é obrigatório no RoutePoint');
            }
        });
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_optional', false);
    }

    public function getIsStartPointAttribute()
    {
        return $this->type === 'start';
    }

    public function getIsEndPointAttribute()
    {
        return $this->type === 'end';
    }

    public function getIsIntermediatePointAttribute()
    {
        return $this->type === 'intermediate';
    }

    public function getFormattedStopDurationAttribute()
    {
        if (!$this->stop_duration) return null;

        if ($this->stop_duration < 60) {
            return "{$this->stop_duration} min";
        }

        $hours = floor($this->stop_duration / 60);
        $minutes = $this->stop_duration % 60;

        return $minutes > 0 ? "{$hours}h {$minutes}min" : "{$hours}h";
    }

    // ⭐ MÉTODOS PARA ACESSAR DADOS DO PONTO
    public function getPointNameAttribute()
    {
        return $this->point->name ?? null;
    }

    public function getPointLatitudeAttribute()
    {
        return $this->point->latitude ?? null;
    }

    public function getPointLongitudeAttribute()
    {
        return $this->point->longitude ?? null;
    }

    public function getPointLocationAttribute()
    {
        return $this->point->location ?? null;
    }

    public function getPointTypeAttribute()
    {
        return $this->point->type ?? null;
    }

    // ⭐ MÉTODO PARA ENCONTRAR PONTOS PRÓXIMOS (usando o ponto associado)
    public function findNearbyPoints(float $distanceMeters = 2000, ?int $limit = null)
    {
        if (!$this->point) {
            return collect();
        }

        return $this->point->findNearbyPoints($distanceMeters, $limit);
    }

    // ⭐ MÉTODO ESTÁTICO PARA CRIAR RoutePoint COM VALIDAÇÃO
    public static function createWithValidation(array $data)
    {
        // Validar dados obrigatórios
        if (!isset($data['route_id']) || !$data['route_id']) {
            throw new \InvalidArgumentException('route_id é obrigatório');
        }

        if (!isset($data['point_id']) || !$data['point_id']) {
            throw new \InvalidArgumentException('point_id é obrigatório');
        }

        // Verificar se route e point existem
        if (!Route::find($data['route_id'])) {
            throw new \InvalidArgumentException('Rota não encontrada');
        }

        if (!Point::find($data['point_id'])) {
            throw new \InvalidArgumentException('Ponto não encontrado');
        }

        // Verificar duplicatas
        $existing = self::where('route_id', $data['route_id'])
            ->where('point_id', $data['point_id'])
            ->where('sequence', $data['sequence'] ?? 0)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Este ponto já está associado a esta rota nesta sequência');
        }

        return self::create($data);
    }
}

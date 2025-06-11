<?php

namespace App\Models\Routes;

use App\Models\Institution;
use App\Models\Permission;
use App\Models\Point;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'institution_id',
        'schedule_type',
        'schedule_data',
        'total_distance',
        'total_duration',
        'is_published',
        'is_public',
        'last_calculated_at',
        'calculation_status',
        'calculation_started_at',
        'calculation_completed_at',
        'calculation_error',
    ];

    protected $casts = [
        'schedule_data' => 'array',
        'total_distance' => 'float',
        'total_duration' => 'float',
        'is_published' => 'boolean',
        'is_public' => 'boolean',
        'last_calculated_at' => 'datetime',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
    ];

    /**
     * Relacionamento com a instituição
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Relacionamento com os pontos da rota
     */
    public function points(): BelongsToMany
    {
        return $this->belongsToMany(
            Point::class,
            'route_points', // tabela intermediária
            'route_id',     // FK desta model na tabela intermediária
            'point_id'      // FK da model relacionada na tabela intermediária
        )->withPivot([
            'sequence',
            'type',
            'stop_duration',
            'is_optional',
            'route_specific_notes',
            'arrival_time',
            'departure_time'
        ])->orderBy('route_points.sequence');
    }

    /**
     * Relacionamento com os segmentos da rota
     */
    public function segments(): HasMany
    {
        return $this->hasMany(RouteSegment::class)->orderBy('sequence');
    }

    /**
     * Relacionamento com as permissões necessárias para acessar esta rota
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'route_permissions')
            ->withTimestamps();
    }

    /**
     * Scope para rotas publicadas
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope para rotas públicas
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope para rotas de uma instituição específica
     */
    public function scopeForInstitution($query, $institutionId)
    {
        return $query->where('institution_id', $institutionId);
    }

    /**
     * Scope para rotas com status de cálculo específico
     */
    public function scopeWithCalculationStatus($query, $status)
    {
        return $query->where('calculation_status', $status);
    }

    /**
     * Scope para busca por nome
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'ILIKE', "%{$search}%")
            ->orWhere('description', 'ILIKE', "%{$search}%");
    }

    /**
     * Verifica se a rota está publicada
     */
    public function isPublished(): bool
    {
        return $this->is_published;
    }

    /**
     * Verifica se a rota é pública
     */
    public function isPublic(): bool
    {
        return $this->is_public;
    }

    /**
     * Verifica se a rota está calculada
     */
    public function isCalculated(): bool
    {
        return $this->calculation_status === 'completed';
    }

    /**
     * Verifica se a rota está sendo calculada
     */
    public function isCalculating(): bool
    {
        return $this->calculation_status === 'calculating';
    }

    /**
     * Obtém o primeiro ponto da rota
     */
    public function getStartPoint(): ?RoutePoint
    {
        return $this->points()->orderBy('sequence')->first();
    }

    /**
     * Obtém o último ponto da rota
     */
    public function getEndPoint(): ?RoutePoint
    {
        return $this->points()->orderBy('sequence', 'desc')->first();
    }

    /**
     * Obtém a duração total formatada
     */
    public function getFormattedDuration(): string
    {
        $hours = floor($this->total_duration / 3600);
        $minutes = floor(($this->total_duration % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Obtém a distância total formatada
     */
    public function getFormattedDistance(): string
    {
        if ($this->total_distance >= 1000) {
            return sprintf('%.1f km', $this->total_distance / 1000);
        }

        return sprintf('%.0f m', $this->total_distance);
    }

    /**
     * Verifica se um usuário tem permissão para acessar esta rota
     */
    public function userHasAccess($user): bool
    {
        // Rotas públicas são acessíveis a todos usuários autenticados
        if ($this->is_public && $this->is_published) {
            return true;
        }

        // Verificar se o usuário é admin
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Verificar se o usuário pertence à instituição da rota
        if ($user && method_exists($user, 'getInstitutions')) {
            $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();

            if (!in_array($this->institution_id, $userInstitutionIds)) {
                return false;
            }
        }

        // Se a rota tem permissões específicas, verificar se o usuário tem essas permissões
        if ($this->permissions->count() > 0) {
            if (!$user || !method_exists($user, 'permissions')) {
                return false;
            }

            $userPermissions = $user->permissions()->pluck('permissions.id')->toArray();
            $routePermissions = $this->permissions->pluck('id')->toArray();

            return !empty(array_intersect($userPermissions, $routePermissions));
        }

        return true;
    }

    /**
     * Obtém os dados de agendamento formatados
     */
    public function getScheduleInfo(): array
    {
        $scheduleData = $this->schedule_data ?? [];

        switch ($this->schedule_type) {
            case 'daily':
                $days = $scheduleData['days'] ?? [];
                $dayNames = [
                    0 => 'Domingo',
                    1 => 'Segunda',
                    2 => 'Terça',
                    3 => 'Quarta',
                    4 => 'Quinta',
                    5 => 'Sexta',
                    6 => 'Sábado'
                ];

                return [
                    'type' => 'Diário',
                    'start_time' => $scheduleData['start_time'] ?? null,
                    'end_time' => $scheduleData['end_time'] ?? null,
                    'days' => array_map(fn($day) => $dayNames[$day] ?? $day, $days),
                    'description' => sprintf(
                        '%s às %s (%s)',
                        $scheduleData['start_time'] ?? '--:--',
                        $scheduleData['end_time'] ?? '--:--',
                        implode(', ', array_map(fn($day) => $dayNames[$day] ?? $day, $days))
                    )
                ];

            case 'weekly':
                return [
                    'type' => 'Semanal',
                    'description' => 'Agendamento semanal'
                ];

            case 'monthly':
                return [
                    'type' => 'Mensal',
                    'description' => 'Agendamento mensal'
                ];

            default:
                return [
                    'type' => 'Personalizado',
                    'description' => 'Agendamento personalizado'
                ];
        }
    }

    /**
     * Retorna as estatísticas desta rota
     */
    public function getStats(): array
    {
        return [
            'total_points' => $this->points()->count(),
            'total_segments' => $this->segments()->count(),
            'is_calculated' => $this->isCalculated(),
            'calculation_status' => $this->calculation_status,
            'total_distance_formatted' => $this->getFormattedDistance(),
            'total_duration_formatted' => $this->getFormattedDuration(),
            'permissions_count' => $this->permissions()->count(),
        ];
    }

    /**
     * Obtém o status de cálculo com informações detalhadas
     */
    public function getCalculationStatusInfo(): array
    {
        return [
            'status' => $this->calculation_status,
            'started_at' => $this->calculation_started_at,
            'completed_at' => $this->calculation_completed_at,
            'error' => $this->calculation_error,
            'is_calculating' => $this->isCalculating(),
            'is_completed' => $this->isCalculated(),
            'duration_seconds' => $this->calculation_started_at && $this->calculation_completed_at
                ? $this->calculation_completed_at->diffInSeconds($this->calculation_started_at)
                : null,
        ];
    }

    /**
     * Marca o início do cálculo da rota
     */
    public function markCalculationStarted(): void
    {
        $this->update([
            'calculation_status' => 'calculating',
            'calculation_started_at' => now(),
            'calculation_completed_at' => null,
            'calculation_error' => null,
        ]);
    }

    /**
     * Marca o cálculo como concluído
     */
    public function markCalculationCompleted(): void
    {
        $this->update([
            'calculation_status' => 'completed',
            'calculation_completed_at' => now(),
            'last_calculated_at' => now(),
            'calculation_error' => null,
        ]);
    }

    /**
     * Marca o cálculo como com erro
     */
    public function markCalculationFailed(string $error): void
    {
        $this->update([
            'calculation_status' => 'failed',
            'calculation_completed_at' => now(),
            'calculation_error' => $error,
        ]);
    }

    /**
     * Reset do status de cálculo
     */
    public function resetCalculationStatus(): void
    {
        $this->update([
            'calculation_status' => 'not_started',
            'calculation_started_at' => null,
            'calculation_completed_at' => null,
            'calculation_error' => null,
        ]);
    }

    public function startPoint()
    {
        return $this->belongsToMany(
            Point::class,
            'route_points',
            'route_id',
            'point_id'
        )->wherePivot('type', 'start')
            ->withPivot([
                'sequence',
                'type',
                'stop_duration',
                'is_optional',
                'route_specific_notes',
                'arrival_time',
                'departure_time'
            ])->orderBy('route_points.sequence')
            ->limit(1);
    }

    /**
     * Ponto final da rota
     */
    public function endPoint()
    {
        return $this->belongsToMany(
            Point::class,
            'route_points',
            'route_id',
            'point_id'
        )->wherePivot('type', 'end')
            ->withPivot([
                'sequence',
                'type',
                'stop_duration',
                'is_optional',
                'route_specific_notes',
                'arrival_time',
                'departure_time'
            ])->orderBy('route_points.sequence')
            ->limit(1);
    }

    /**
     * Pontos intermediários da rota
     */
    public function intermediatePoints()
    {
        return $this->belongsToMany(
            Point::class,
            'route_points',
            'route_id',
            'point_id'
        )->wherePivot('type', 'intermediate')
            ->withPivot([
                'sequence',
                'type',
                'stop_duration',
                'is_optional',
                'route_specific_notes',
                'arrival_time',
                'departure_time'
            ])->orderBy('route_points.sequence');
    }

    // ⭐ ACESSORS PARA INFORMAÇÕES DOS PONTOS

    public function getTotalPointsAttribute()
    {
        return $this->routePoints()->count();
    }

    public function getFormattedRouteAttribute()
    {
        $startPoint = $this->startPoint()->first();
        $endPoint = $this->endPoint()->first();
        $intermediateCount = $this->intermediatePoints()->count();

        $start = $startPoint->name ?? 'Início';
        $end = $endPoint->name ?? 'Fim';

        if ($intermediateCount > 0) {
            return "{$start} → (+{$intermediateCount}) → {$end}";
        }

        return "{$start} → {$end}";
    }

    public function getPointsOrderedAttribute()
    {
        return $this->points()->orderBy('route_points.sequence')->get();
    }

    // ⭐ MÉTODOS PARA MANIPULAR PONTOS

    /**
     * Adicionar ponto à rota
     */
    public function addPoint(Point $point, array $routePointData = [])
    {
        $defaultData = [
            'sequence' => $this->routePoints()->max('sequence') + 1,
            'type' => 'intermediate',
            'stop_duration' => null,
            'is_optional' => false,
            'route_specific_notes' => null,
            'arrival_time' => null,
            'departure_time' => null,
        ];

        $data = array_merge($defaultData, $routePointData, [
            'route_id' => $this->id,
            'point_id' => $point->id
        ]);

        return RoutePoint::createWithValidation($data);
    }

    /**
     * Remover ponto da rota
     */
    public function removePoint(Point $point)
    {
        return $this->routePoints()->where('point_id', $point->id)->delete();
    }

    /**
     * Reordenar pontos da rota
     */
    public function reorderPoints(array $pointSequences)
    {
        foreach ($pointSequences as $pointId => $sequence) {
            $this->routePoints()
                ->where('point_id', $pointId)
                ->update(['sequence' => $sequence]);
        }

        // Atualizar tipos baseado na nova ordem
        $this->updatePointTypes();
    }

    /**
     * Atualizar tipos dos pontos baseado na sequência
     */
    public function updatePointTypes()
    {
        $routePoints = $this->routePoints()->orderBy('sequence')->get();

        if ($routePoints->isEmpty()) return;

        // Primeiro ponto = start
        $first = $routePoints->first();
        $first->update(['type' => 'start']);

        // Último ponto = end
        if ($routePoints->count() > 1) {
            $last = $routePoints->last();
            $last->update(['type' => 'end']);
        }

        // Pontos do meio = intermediate
        if ($routePoints->count() > 2) {
            $routePoints->slice(1, -1)->each(function ($routePoint) {
                $routePoint->update(['type' => 'intermediate']);
            });
        }
    }

    // ⭐ MÉTODOS DE CONSULTA

    /**
     * Buscar rotas que passam por um ponto específico
     */
    public static function passingThroughPoint(Point $point)
    {
        return self::whereHas('routePoints', function ($query) use ($point) {
            $query->where('point_id', $point->id);
        });
    }

    /**
     * Buscar rotas próximas a uma localização
     */
    public static function nearLocation($latitude, $longitude, $distanceMeters = 2000)
    {
        $nearbyPoints = Point::findNearby($latitude, $longitude, $distanceMeters);
        $pointIds = $nearbyPoints->pluck('id');

        return self::whereHas('routePoints', function ($query) use ($pointIds) {
            $query->whereIn('point_id', $pointIds);
        });
    }
}

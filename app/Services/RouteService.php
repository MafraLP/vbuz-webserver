<?php

namespace App\Services;

use App\Models\Routes\Route;
use App\Models\Routes\RoutePoint;
use App\Models\Routes\RouteSegment;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RouteService
{
    protected $client;
    protected $baseUrl = 'https://api.openrouteservice.org/v2';
    protected $apiKey;
    protected $cacheExpirationHours = 24;

    public function __construct()
    {
        $this->apiKey = config('services.openrouteservice.api_key');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Calcula ou recupera do cache um segmento de rota entre dois pontos
     */
    public function getRouteSegment(RoutePoint $startPoint, RoutePoint $endPoint, string $profile = 'driving-car'): array
    {
        // Gerar uma chave de cache única para este segmento
        $cacheKey = $this->generateSegmentCacheKey($startPoint, $endPoint, $profile);

        // Verificar se temos este segmento em cache
        if (Cache::has($cacheKey)) {
            Log::info("Segmento recuperado do cache: {$cacheKey}");
            return Cache::get($cacheKey);
        }

        // Se não está em cache, calcular
        try {
            $response = $this->client->post("/directions/{$profile}/geojson", [
                'json' => [
                    'coordinates' => [
                        [$startPoint->longitude, $startPoint->latitude],
                        [$endPoint->longitude, $endPoint->latitude]
                    ],
                    'preference' => 'recommended',
                    'units' => 'km',
                    'language' => 'pt',
                    'geometry_simplify' => false,
                    'instructions' => true
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['features']) || empty($data['features'])) {
                throw new Exception('Nenhuma rota encontrada');
            }

            $feature = $data['features'][0];
            $segmentData = [
                'distance' => $feature['properties']['summary']['distance'],
                'duration' => $feature['properties']['summary']['duration'],
                'geometry' => json_encode($feature['geometry']),
                'encoded_polyline' => $this->encodePolyline($feature['geometry']['coordinates']),
                'cache_key' => $cacheKey,
                'profile' => $profile,
            ];

            // Armazenar no cache
            Cache::put($cacheKey, $segmentData, Carbon::now()->addHours($this->cacheExpirationHours));

            return $segmentData;
        } catch (GuzzleException $e) {
            Log::error("Erro ao calcular rota: " . $e->getMessage());
            throw new Exception('Falha ao calcular rota: ' . $e->getMessage());
        }
    }

    /**
     * Calcula ou atualiza uma rota completa entre múltiplos pontos
     */
    public function calculateRoute(Route $route, bool $forceRecalculate = false): Route
    {
        $points = $route->points()->orderBy('sequence')->get();

        if ($points->count() < 2) {
            throw new Exception('São necessários pelo menos 2 pontos para calcular uma rota');
        }

        // Apagar segmentos existentes se estamos recalculando à força
        if ($forceRecalculate) {
            $route->segments()->delete();
        }

        $totalDistance = 0;
        $totalDuration = 0;

        // Calcular cada segmento entre pontos consecutivos
        for ($i = 0; $i < $points->count() - 1; $i++) {
            $startPoint = $points[$i];
            $endPoint = $points[$i + 1];

            // Verificar se já temos esse segmento (se não estamos recalculando à força)
            $segment = null;
            if (!$forceRecalculate) {
                $segment = $route->segments()
                    ->where('start_point_id', $startPoint->id)
                    ->where('end_point_id', $endPoint->id)
                    ->first();
            }

            // Se o segmento não existe ou está expirado, calcular novamente
            if (!$segment || Carbon::parse($segment->expires_at)->isPast()) {
                $segmentData = $this->getRouteSegment($startPoint, $endPoint);

                // Criar ou atualizar o segmento
                if (!$segment) {
                    $segment = new RouteSegment([
                        'route_id' => $route->id,
                        'sequence' => $i,
                        'start_point_id' => $startPoint->id,
                        'end_point_id' => $endPoint->id,
                    ]);
                }

                $segment->fill([
                    'distance' => $segmentData['distance'],
                    'duration' => $segmentData['duration'],
                    'geometry' => $segmentData['geometry'],
                    'encoded_polyline' => $segmentData['encoded_polyline'],
                    'profile' => $segmentData['profile'],
                    'cache_key' => $segmentData['cache_key'],
                    'expires_at' => Carbon::now()->addHours($this->cacheExpirationHours),
                ]);

                $segment->save();
            }

            // Acumular distância e duração
            $totalDistance += $segment->distance;
            $totalDuration += $segment->duration;
        }

        // Atualizar totais na rota
        $route->update([
            'total_distance' => $totalDistance,
            'total_duration' => $totalDuration,
            'last_calculated_at' => Carbon::now(),
        ]);

        return $route->fresh();
    }

    /**
     * Recalcula uma rota após remover um ponto
     */
    public function recalculateAfterPointRemoval(Route $route, int $removedPointSequence): Route
    {
        // Precisamos recalcular apenas o segmento antes e depois do ponto removido
        // Ou seja, conectando os pontos que estavam adjacentes ao ponto removido

        // Reordenar sequências dos pontos
        $this->reorderRoutePoints($route);

        // Remover os segmentos afetados
        $route->segments()
            ->where('sequence', $removedPointSequence - 1)
            ->orWhere('sequence', $removedPointSequence)
            ->delete();

        // Se o ponto removido não era nem o primeiro nem o último,
        // precisamos criar um novo segmento conectando os pontos adjacentes
        $points = $route->points()->orderBy('sequence')->get();

        if ($removedPointSequence > 0 && $removedPointSequence < $points->count()) {
            $prevPoint = $route->points()->where('sequence', $removedPointSequence - 1)->first();
            $nextPoint = $route->points()->where('sequence', $removedPointSequence)->first();

            if ($prevPoint && $nextPoint) {
                $segmentData = $this->getRouteSegment($prevPoint, $nextPoint);

                RouteSegment::create([
                    'route_id' => $route->id,
                    'sequence' => $removedPointSequence - 1,
                    'start_point_id' => $prevPoint->id,
                    'end_point_id' => $nextPoint->id,
                    'distance' => $segmentData['distance'],
                    'duration' => $segmentData['duration'],
                    'geometry' => $segmentData['geometry'],
                    'encoded_polyline' => $segmentData['encoded_polyline'],
                    'profile' => $segmentData['profile'],
                    'cache_key' => $segmentData['cache_key'],
                    'expires_at' => Carbon::now()->addHours($this->cacheExpirationHours),
                ]);
            }
        }

        // Reordenar segmentos
        $this->reorderRouteSegments($route);

        // Recalcular totais
        $this->updateRouteTotals($route);

        return $route->fresh();
    }

    /**
     * Gera uma chave de cache para um segmento de rota
     */
    protected function generateSegmentCacheKey(RoutePoint $startPoint, RoutePoint $endPoint, string $profile): string
    {
        $start = number_format($startPoint->latitude, 6) . ',' . number_format($startPoint->longitude, 6);
        $end = number_format($endPoint->latitude, 6) . ',' . number_format($endPoint->longitude, 6);
        return "route_segment_{$profile}_{$start}_{$end}";
    }

    /**
     * Reordena os pontos de uma rota para garantir sequência contínua
     */
    protected function reorderRoutePoints(Route $route): void
    {
        $points = $route->points()->orderBy('sequence')->get();
        foreach ($points as $index => $point) {
            if ($point->sequence !== $index) {
                $point->update(['sequence' => $index]);
            }
        }
    }

    /**
     * Reordena os segmentos de uma rota
     */
    protected function reorderRouteSegments(Route $route): void
    {
        $segments = $route->segments()->orderBy('sequence')->get();
        foreach ($segments as $index => $segment) {
            if ($segment->sequence !== $index) {
                $segment->update(['sequence' => $index]);
            }
        }
    }

    /**
     * Atualiza os totais de uma rota com base nos segmentos
     */
    protected function updateRouteTotals(Route $route): void
    {
        $totals = $route->segments()->selectRaw('SUM(distance) as total_distance, SUM(duration) as total_duration')->first();

        $route->update([
            'total_distance' => $totals->total_distance ?? 0,
            'total_duration' => $totals->total_duration ?? 0,
            'last_calculated_at' => Carbon::now(),
        ]);
    }

    /**
     * Codifica coordenadas para formato polyline
     */
    protected function encodePolyline(array $coordinates): string
    {
        // Implementação do algoritmo de codificação de polyline do Google
        // Simplificada para este exemplo
        return 'encoded_polyline_string';
    }

    /**
     * Retorna os dados da rota formatados para o frontend
     */
    public function getRouteForFrontend(Route $route): array
    {
        $route->load(['points' => function ($query) {
            $query->orderBy('sequence');
        }, 'segments' => function ($query) {
            $query->orderBy('sequence');
        }]);

        return [
            'id' => $route->id,
            'name' => $route->name,
            'total_distance' => $route->total_distance,
            'total_duration' => $route->total_duration,
            'last_calculated_at' => $route->last_calculated_at,
            'points' => $route->points->map(function ($point) {
                return [
                    'id' => $point->id,
                    'sequence' => $point->sequence,
                    'name' => $point->name,
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ];
            }),
            'segments' => $route->segments->map(function ($segment) {
                return [
                    'id' => $segment->id,
                    'sequence' => $segment->sequence,
                    'distance' => $segment->distance,
                    'duration' => $segment->duration,
                    'geometry' => json_decode($segment->geometry),
                    'encoded_polyline' => $segment->encoded_polyline,
                ];
            }),
        ];
    }
}

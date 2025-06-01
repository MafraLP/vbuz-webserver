<?php

namespace App\Services;

use App\Models\Routes\Route;
use App\Models\Routes\RouteSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Client\Pool;

class RouteCalculationService
{
    /**
     * Calcula segmentos para uma rota de forma SUPER otimizada
     */
    public function calculateRouteSegments(Route $route): void
    {
        $startTime = microtime(true);

        try {
            // Limpar segmentos antigos
            $step1 = microtime(true);
            DB::table('route_segments')->where('route_id', $route->id)->delete();

            // Ordenar os pontos por sequência
            $step2 = microtime(true);
            $points = $route->points()->orderBy('sequence')->get();

            if ($points->count() < 2) {

                return;
            }

            $step3 = microtime(true);
            $pointPairs = $this->preparePointPairs($points);
            $segmentCount = count($pointPairs);

            $provider = config('routing.provider', 'openrouteservice');

            // Para rotas pequenas (até 10 segmentos), usar processamento paralelo
            $step4 = microtime(true);
            if ($segmentCount <= 10) {
                $segments = $this->calculateSegmentsParallel($pointPairs, $route);
            } else {
                // Para rotas grandes, usar lotes otimizados
                $segments = $this->calculateSegmentsBatch($pointPairs, $route);
            }

            // Inserir todos os segmentos de uma vez
            $step5 = microtime(true);
            if (!empty($segments)) {
                DB::table('route_segments')->insert($segments);

                $step6 = microtime(true);
                $totalDistance = array_sum(array_column($segments, 'distance'));
                $totalDuration = array_sum(array_column($segments, 'duration'));

                $step7 = microtime(true);
                $this->updateRouteCalculation($route, $totalDistance, $totalDuration, 'completed');

            } else {
                $this->updateRouteCalculation($route, 0, 0, 'error', 'Nenhum segmento calculado');
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);


        } catch (Exception $e) {
            $errorTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->updateRouteCalculation($route, 0, 0, 'error', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Calcula segmentos em paralelo (para rotas pequenas)
     */
    private function calculateSegmentsParallel(array $pointPairs, Route $route): array
    {
        $parallelStartTime = microtime(true);

        $profile = config('routing.default_profile', 'driving-car');
        $segments = [];

        // Verificar cache primeiro para todos os pares
        $cacheStep = microtime(true);
        $cacheResults = $this->checkBulkCache($pointPairs, $profile);

        $uncachedPairs = [];

        $loopStart = microtime(true);
        foreach ($pointPairs as $index => $pair) {
            $cacheKey = $this->generateCacheKey($pair['start'], $pair['end'], $profile);

            if (isset($cacheResults[$cacheKey])) {
                // Usar dados do cache
                $segments[] = $this->buildSegmentArray(
                    $route,
                    $pair['sequence'],
                    $pair['start'],
                    $pair['end'],
                    $cacheResults[$cacheKey],
                    $profile,
                    $cacheKey
                );

            } else {
                // Adicionar à lista para cálculo paralelo
                $uncachedPairs[] = $pair;
            }
        }

        // Se há pares não-cachados, calcular em paralelo
        if (!empty($uncachedPairs)) {
            $apiStep = microtime(true);
            $parallelResults = $this->calculateParallelRequests($uncachedPairs, $profile);

            $buildStep = microtime(true);
            foreach ($uncachedPairs as $index => $pair) {
                $segmentData = $parallelResults[$index] ?? $this->createFallbackSegment(
                    $pair['start']->latitude,
                    $pair['start']->longitude,
                    $pair['end']->latitude,
                    $pair['end']->longitude
                );

                $cacheKey = $this->generateCacheKey($pair['start'], $pair['end'], $profile);

                $segments[] = $this->buildSegmentArray(
                    $route,
                    $pair['sequence'],
                    $pair['start'],
                    $pair['end'],
                    $segmentData,
                    $profile,
                    $cacheKey,
                    true
                );

                // Salvar no cache
                $this->cacheSegment($cacheKey, $segmentData);

            }

        }

        // Ordenar segmentos por sequência
        $sortStep = microtime(true);
        usort($segments, function($a, $b) {
            return $a['sequence'] <=> $b['sequence'];
        });

        $parallelTotal = round((microtime(true) - $parallelStartTime) * 1000, 2);

        return $segments;
    }

    /**
     * Faz requisições paralelas para a API (OSRM ou OpenRouteService)
     */
    private function calculateParallelRequests(array $pointPairs, string $profile): array
    {
        $reqStart = microtime(true);

        if (config('routing.provider') === 'osrm' && config('routing.osrm.enabled')) {
            $result = $this->calculateParallelRequestsOSRM($pointPairs);
        } else {
            $result = $this->calculateParallelRequestsOpenRoute($pointPairs, $profile);
        }

        $reqTotal = round((microtime(true) - $reqStart) * 1000, 2);

        return $result;
    }

    /**
     * Requisições paralelas para OSRM (super rápidas)
     */
    private function calculateParallelRequestsOSRM(array $pointPairs): array
    {
        $osrmStart = microtime(true);

        $baseUrl = config('routing.osrm.base_url', 'http://osrm-backend:5000');
        $results = [];

        try {
            $poolStart = microtime(true);
            $responses = Http::pool(function (Pool $pool) use ($pointPairs, $baseUrl) {
                $requests = [];

                foreach ($pointPairs as $index => $pair) {
                    $url = "{$baseUrl}/route/v1/driving/{$pair['start']->longitude},{$pair['start']->latitude};{$pair['end']->longitude},{$pair['end']->latitude}";

                    $requests[] = $pool->timeout(config('routing.osrm.timeout', 2))
                        ->get($url, [
                            'overview' => 'full',
                            'geometries' => 'geojson',
                            'steps' => 'false'
                        ]);
                }

                return $requests;
            });

            $processStart = microtime(true);
            foreach ($responses as $index => $response) {
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['routes'][0])) {
                        $route = $data['routes'][0];
                        $results[$index] = [
                            'distance' => $route['distance'],
                            'duration' => $route['duration'],
                            'geometry' => $route['geometry'],
                            'encoded_polyline' => json_encode($route['geometry']) // Converter para string
                        ];
                        continue;
                    }
                }

                // Fallback para este par específico
                $pair = $pointPairs[$index];
                $results[$index] = $this->createFallbackSegment(
                    $pair['start']->latitude,
                    $pair['start']->longitude,
                    $pair['end']->latitude,
                    $pair['end']->longitude
                );
            }

        } catch (Exception $e) {

            // Fallback para todos
            foreach ($pointPairs as $index => $pair) {
                $results[$index] = $this->createFallbackSegment(
                    $pair['start']->latitude,
                    $pair['start']->longitude,
                    $pair['end']->latitude,
                    $pair['end']->longitude
                );
            }
        }

        $osrmTotal = round((microtime(true) - $osrmStart) * 1000, 2);

        return $results;
    }

    private function updateRouteCalculation(Route $route, float $totalDistance, float $totalDuration, string $status, ?string $error = null): void
    {
        $updateStart = microtime(true);


        try {
            $dataStep = microtime(true);
            $updateData = [
                'total_distance' => round($totalDistance / 1000, 3),
                'total_duration' => round($totalDuration / 60, 2),
                'calculation_status' => $status
            ];

            if ($status === 'completed') {
                $updateData['last_calculated_at'] = now();
                $updateData['calculation_completed_at'] = now();
                $updateData['calculation_error'] = null;
            } elseif ($status === 'error' || $status === 'failed') {
                $updateData['calculation_error'] = $error;
            }

            $dbStep = microtime(true);
            $route->update($updateData);


        } catch (Exception $e) {

            throw $e;
        }

        $updateTotal = round((microtime(true) - $updateStart) * 1000, 2);

    }

    // === MÉTODOS UTILITÁRIOS INALTERADOS ===

    /**
     * Requisições paralelas para OpenRouteService (original)
     */
    private function calculateParallelRequestsOpenRoute(array $pointPairs, string $profile): array
    {
        $apiKey = config('services.openrouteservice.api_key');
        $baseUrl = config('services.openrouteservice.base_url', 'https://api.openrouteservice.org/v2/directions');
        $url = "{$baseUrl}/{$profile}";

        $results = [];

        try {
            $responses = Http::pool(function (Pool $pool) use ($pointPairs, $url, $apiKey) {
                $requests = [];

                foreach ($pointPairs as $index => $pair) {
                    $coordinates = [
                        [$pair['start']->longitude, $pair['start']->latitude],
                        [$pair['end']->longitude, $pair['end']->latitude]
                    ];

                    $requests[] = $pool->timeout(config('routing.openrouteservice.timeout', 5))
                        ->withHeaders([
                            'Authorization' => $apiKey,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ])
                        ->post($url, [
                            'coordinates' => $coordinates,
                            'format' => 'geojson',
                            'instructions' => false,
                            'geometry_simplify' => true
                        ]);
                }

                return $requests;
            });

            foreach ($responses as $index => $response) {
                if ($response->successful()) {
                    $routeData = $response->json();
                    if (!empty($routeData['routes']) && isset($routeData['routes'][0])) {
                        $results[$index] = $this->parseSuccessfulResponse($routeData['routes'][0]);
                        continue;
                    }
                }

                // Fallback para esta requisição específica
                $pair = $pointPairs[$index];
                $results[$index] = $this->createFallbackSegment(
                    $pair['start']->latitude,
                    $pair['start']->longitude,
                    $pair['end']->latitude,
                    $pair['end']->longitude
                );
            }

        } catch (Exception $e) {

            // Fallback para todos os pares
            foreach ($pointPairs as $index => $pair) {
                $results[$index] = $this->createFallbackSegment(
                    $pair['start']->latitude,
                    $pair['start']->longitude,
                    $pair['end']->latitude,
                    $pair['end']->longitude
                );
            }
        }

        return $results;
    }

    private function checkBulkCache(array $pointPairs, string $profile): array
    {
        $cacheKeys = [];
        foreach ($pointPairs as $pair) {
            $cacheKeys[] = $this->generateCacheKey($pair['start'], $pair['end'], $profile);
        }

        // Buscar todos os caches de uma vez
        $cacheResults = [];
        $cacheTime = config('routing.cache_duration', 30 * 24 * 60 * 60);

        foreach ($cacheKeys as $cacheKey) {
            $cached = Cache::get("segment_cache_{$cacheKey}");
            if ($cached && isset($cached['expires_at']) && $cached['expires_at'] > now()) {
                $cacheResults[$cacheKey] = $cached;
            }
        }

        return $cacheResults;
    }

    private function calculateSegmentsBatch(array $pointPairs, Route $route): array
    {
        $profile = config('routing.default_profile', 'driving-car');
        $batchSize = min(config('routing.batch_size', 10), 10); // Máximo 10 por lote
        $segments = [];

        $batches = array_chunk($pointPairs, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $batchSegments = $this->processBatchOptimized($batch, $route, $profile);
            $segments = array_merge($segments, $batchSegments);

            // Atualizar progresso sem pausas
            $this->updateCalculationProgress($route, count($segments), count($pointPairs));
        }

        return $segments;
    }

    private function processBatchOptimized(array $batch, Route $route, string $profile): array
    {
        $segments = [];

        foreach ($batch as $pair) {
            $cacheKey = $this->generateCacheKey($pair['start'], $pair['end'], $profile);
            $cachedSegment = Cache::get("segment_cache_{$cacheKey}");

            if ($cachedSegment && isset($cachedSegment['expires_at']) && $cachedSegment['expires_at'] > now()) {
                $segments[] = $this->buildSegmentArray($route, $pair['sequence'], $pair['start'], $pair['end'], $cachedSegment, $profile, $cacheKey);
            } else {
                $segmentData = $this->calculateSingleSegment($pair['start'], $pair['end'], $profile);
                $segments[] = $this->buildSegmentArray($route, $pair['sequence'], $pair['start'], $pair['end'], $segmentData, $profile, $cacheKey, true);
                $this->cacheSegment($cacheKey, $segmentData);
            }
        }

        return $segments;
    }

    private function calculateSingleSegment($startPoint, $endPoint, string $profile): array
    {
        if (config('routing.provider') === 'osrm' && config('routing.osrm.enabled')) {
            try {
                return $this->callOSRMLocal($startPoint->latitude, $startPoint->longitude, $endPoint->latitude, $endPoint->longitude);
            } catch (Exception $e) {

            }
        }

        // Fallback para OpenRouteService
        return $this->callOpenRouteService($startPoint->latitude, $startPoint->longitude, $endPoint->latitude, $endPoint->longitude, $profile);
    }

    private function callOSRMLocal(float $startLat, float $startLon, float $endLat, float $endLon): array
    {
        $baseUrl = config('routing.osrm.base_url', 'http://osrm-backend:5000');
        $url = "{$baseUrl}/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}";

        $response = Http::timeout(config('routing.osrm.timeout', 2))
            ->retry(config('routing.osrm.retries', 2), config('routing.osrm.retry_delay', 100))
            ->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false'
            ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['routes'][0])) {
                $route = $data['routes'][0];

                return [
                    'distance' => $route['distance'], // metros
                    'duration' => $route['duration'], // segundos
                    'geometry' => $route['geometry'], // GeoJSON
                    'encoded_polyline' => json_encode($route['geometry']) // Converter para string
                ];
            }
        }

        throw new Exception("OSRM retornou resposta inválida. Status: " . $response->status() . ", Body: " . $response->body());
    }

    private function callOpenRouteService(float $startLat, float $startLon, float $endLat, float $endLon, string $profile): array
    {
        $apiKey = config('services.openrouteservice.api_key');
        $baseUrl = config('services.openrouteservice.base_url', 'https://api.openrouteservice.org/v2/directions');
        $url = "{$baseUrl}/{$profile}";

        $coordinates = [
            [$startLon, $startLat],
            [$endLon, $endLat]
        ];

        try {
            $response = Http::timeout(config('routing.openrouteservice.timeout', 5))
                ->retry(config('routing.openrouteservice.retries', 2), config('routing.openrouteservice.retry_delay', 500))
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->post($url, [
                    'coordinates' => $coordinates,
                    'format' => 'geojson',
                    'instructions' => false,
                    'geometry_simplify' => config('routing.simplify_geometry', true)
                ]);

            if ($response->successful()) {
                $routeData = $response->json();

                if (!empty($routeData['routes']) && isset($routeData['routes'][0])) {
                    return $this->parseSuccessfulResponse($routeData['routes'][0]);
                }
            }

            if ($response->status() >= 400) {

            }

            return $this->createFallbackSegment($startLat, $startLon, $endLat, $endLon);

        } catch (Exception $e) {

            return $this->createFallbackSegment($startLat, $startLon, $endLat, $endLon);
        }
    }

    private function preparePointPairs($points): array
    {
        $pointPairs = [];
        for ($i = 0; $i < $points->count() - 1; $i++) {
            $pointPairs[] = [
                'start' => $points[$i],
                'end' => $points[$i + 1],
                'sequence' => $i
            ];
        }
        return $pointPairs;
    }

    private function buildSegmentArray(Route $route, int $sequence, $startPoint, $endPoint, array $segmentData, string $profile, string $cacheKey, bool $isNew = false): array
    {
        return [
            'route_id' => $route->id,
            'sequence' => $sequence,
            'start_point_id' => $startPoint->id,
            'end_point_id' => $endPoint->id,
            'distance' => $segmentData['distance'],
            'duration' => $segmentData['duration'],
            'geometry' => is_array($segmentData['geometry']) ? json_encode($segmentData['geometry']) : $segmentData['geometry'],
            'encoded_polyline' => $segmentData['encoded_polyline'],
            'profile' => $profile,
            'cache_key' => $cacheKey,
            'expires_at' => $isNew ? now()->addDays(30) : $segmentData['expires_at'],
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    private function parseSuccessfulResponse(array $route): array
    {
        return [
            'distance' => $route['summary']['distance'] ?? 0,
            'duration' => $route['summary']['duration'] ?? 0,
            'geometry' => $route['geometry'] ?? null,
            'encoded_polyline' => is_array($route['geometry']) ? json_encode($route['geometry']) : $route['geometry']
        ];
    }

    private function createFallbackSegment(float $startLat, float $startLon, float $endLat, float $endLon): array
    {
        return [
            'distance' => $this->calculateHaversineDistance($startLat, $startLon, $endLat, $endLon),
            'duration' => 0,
            'geometry' => null,
            'encoded_polyline' => null
        ];
    }

    private function generateCacheKey($startPoint, $endPoint, string $profile): string
    {
        $provider = config('routing.provider', 'openrouteservice');
        return md5(
            $startPoint->latitude . ',' . $startPoint->longitude . '-' .
            $endPoint->latitude . ',' . $endPoint->longitude . '-' .
            $profile . '-' . $provider
        );
    }

    private function getCachedSegment(string $cacheKey): ?array
    {
        return Cache::get("segment_cache_{$cacheKey}");
    }

    private function cacheSegment(string $cacheKey, array $segmentData): void
    {
        $cacheTime = config('routing.cache_duration', 30 * 24 * 60 * 60);
        Cache::put("segment_cache_{$cacheKey}", array_merge($segmentData, [
            'expires_at' => now()->addDays(30)
        ]), $cacheTime);
    }

    public function updateCalculationProgress(Route $route, int $completed, int $total): void
    {
        $progress = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
        Cache::put("route_calculation_progress_{$route->id}", [
            'completed' => $completed,
            'total' => $total,
            'progress' => $progress,
            'updated_at' => now()
        ], 3600);
    }

    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $latDelta = $lat2Rad - $lat1Rad;
        $lonDelta = $lon2Rad - $lon1Rad;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function calculateProgress(Route $route): float
    {
        $cached = Cache::get("route_calculation_progress_{$route->id}");
        if ($cached) {
            return $cached['progress'];
        }

        $totalSegments = $route->points->count() - 1;
        $calculatedSegments = $route->segments()->count();

        return $totalSegments > 0 ? round(($calculatedSegments / $totalSegments) * 100, 2) : 0;
    }

    public function estimateCalculationTime(int $pointCount): int
    {
        $segmentCount = $pointCount - 1;
        $provider = config('routing.provider', 'openrouteservice');

        if ($provider === 'osrm') {
            // OSRM é muito mais rápido
            if ($segmentCount <= 5) {
                return 1; // 1 segundo para rotas pequenas
            } elseif ($segmentCount <= 20) {
                return max(1, intval($segmentCount * 0.1)); // 0.1 segundo por segmento
            } else {
                return max(2, intval($segmentCount * 0.2)); // 0.2 segundo por segmento
            }
        } else {
            // OpenRouteService (tempo original)
            if ($segmentCount <= 5) {
                return 5;
            } elseif ($segmentCount <= 20) {
                return $segmentCount * 1;
            } else {
                return $segmentCount * 2;
            }
        }
    }

    public function estimateRemainingTime(Route $route): int
    {
        $progress = $this->calculateProgress($route);
        if ($progress >= 100 || $route->calculation_status === 'completed') {
            return 0;
        }

        $totalSegments = $route->points->count() - 1;
        $calculatedSegments = $route->segments()->count();
        $remainingSegments = $totalSegments - $calculatedSegments;

        $provider = config('routing.provider', 'openrouteservice');
        $timePerSegment = $provider === 'osrm' ? 0.1 : 1; // OSRM muito mais rápido

        return max(0, intval($remainingSegments * $timePerSegment));
    }

    public function getCalculationStatus(Route $route): array
    {
        return [
            'route_id' => $route->id,
            'status' => $route->calculation_status ?? 'not_started',
            'started_at' => $route->calculation_started_at,
            'completed_at' => $route->last_calculated_at,
            'total_segments' => $route->points->count() - 1,
            'calculated_segments' => $route->segments()->count(),
            'progress_percentage' => $this->calculateProgress($route),
            'estimated_remaining_seconds' => $this->estimateRemainingTime($route),
            'error_message' => $route->calculation_error,
            'provider' => config('routing.provider', 'openrouteservice')
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Routes\Route;
use App\Models\Routes\RouteSegment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class RouteCalculationService
{
    private bool $useExternalApi;
    private ?string $apiKey;
    private string $osrmUrl;
    private string $externalApiUrl;
    private int $requestTimeout;

    public function __construct()
    {
        // Ler configurações do .env via config
        $this->useExternalApi = config('routing.use_external_api', true); // ← Mudança: padrão true
        $this->apiKey = config('routing.openroute_service_api_key');
        $this->requestTimeout = config('routing.request_timeout', 30);
        $this->externalApiUrl = config('routing.external_api_url', 'https://api.openrouteservice.org');

        // Para OSRM, detectar ambiente automaticamente
        $osrmUrl = config('routing.osrm_url', 'http://localhost:5000');
        if ($osrmUrl === 'http://localhost:5000' && $this->isRunningInDocker()) {
            $this->osrmUrl = env('OSRM_BASE_URL', 'http://osrm-backend:5000');
        } else {
            $this->osrmUrl = $osrmUrl;
        }

        // Validação
        if ($this->useExternalApi && empty($this->apiKey)) {        }    }

    /**
     * Calcula os segmentos de uma rota
     */
    public function calculateRouteSegments(Route $route): void
    {
        $startTime = microtime(true);

        try {            // Limpar segmentos existentes
            $route->segments()->delete();

            $points = $route->points()->orderBy('sequence')->get();

            if ($points->count() < 2) {
                throw new Exception('Rota deve ter pelo menos 2 pontos');
            }

            $totalDistance = 0;
            $totalDuration = 0;

            // Calcular cada segmento
            for ($i = 0; $i < $points->count() - 1; $i++) {
                $fromPoint = $points[$i];
                $toPoint = $points[$i + 1];

                $segmentData = $this->calculateSegment($fromPoint, $toPoint);

                // Criar segmento no banco - CORREÇÃO DOS NOMES DOS CAMPOS
                $segment = new RouteSegment([
                    'route_id' => $route->id,
                    'sequence' => $i,
                    'start_point_id' => $fromPoint->id, // ← from_point_id → start_point_id
                    'end_point_id' => $toPoint->id,     // ← to_point_id → end_point_id
                    'distance' => $segmentData['distance'],
                    'duration' => $segmentData['duration'],
                    'geometry' => $segmentData['geometry'] ?? null,
                    'instructions' => $segmentData['instructions'] ?? null
                ]);

                $segment->save();

                $totalDistance += $segmentData['distance'];
                $totalDuration += $segmentData['duration'];
            }

            // Atualizar totais da rota
            $route->update([
                'total_distance' => round($totalDistance),
                'total_duration' => round($totalDuration),
                'calculation_status' => 'completed',
                'calculation_completed_at' => now(),
                'calculation_error' => null
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);            $route->update([
                'calculation_status' => 'error',
                'calculation_error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Calcula um segmento entre dois pontos
     */
    private function calculateSegment($fromPoint, $toPoint): array
    {
        if ($this->useExternalApi) {
            return $this->calculateSegmentViaExternalApi($fromPoint, $toPoint);
        } else {
            return $this->calculateSegmentViaOsrm($fromPoint, $toPoint);
        }
    }

    /**
     * Calcula segmento usando OSRM local
     */
    private function calculateSegmentViaOsrm($fromPoint, $toPoint): array
    {
        $url = "{$this->osrmUrl}/route/v1/driving/{$fromPoint->longitude},{$fromPoint->latitude};{$toPoint->longitude},{$toPoint->latitude}";

        $params = [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'true'
        ];

        $fullUrl = $url . '?' . http_build_query($params);

        try {
            $response = Http::timeout($this->requestTimeout)->get($fullUrl);

            if (!$response->successful()) {
                throw new Exception("OSRM retornou erro: " . $response->status());
            }

            $data = $response->json();

            if (!isset($data['routes'][0])) {
                throw new Exception('OSRM não retornou rotas válidas');
            }

            $route = $data['routes'][0];

            return [
                'distance' => $route['distance'],
                'duration' => $route['duration'],
                'geometry' => json_encode($route['geometry']),
                'instructions' => $this->formatOsrmInstructions($route['legs'][0]['steps'] ?? [])
            ];

        } catch (Exception $e) {
            Log::error($e);
            throw new Exception("Falha ao calcular rota via OSRM: " . $e->getMessage());
        }
    }

    /**
     * Calcula segmento usando API externa (OpenRouteService)
     */
    private function calculateSegmentViaExternalApi($fromPoint, $toPoint): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('OPENROUTE_SERVICE_API_KEY não configurada');
        }

        $url = "{$this->externalApiUrl}/v2/directions/driving-car";

        $payload = [
            'coordinates' => [
                [$fromPoint->longitude, $fromPoint->latitude],
                [$toPoint->longitude, $toPoint->latitude]
            ],
            'format' => 'json',
            'instructions' => 'true',
            'geometry' => 'true'
        ];

        try {
            $response = Http::timeout($this->requestTimeout)
                ->withHeaders([
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                $errorMsg = "OpenRouteService retornou erro: " . $response->status();
                if ($response->json('error')) {
                    $errorMsg .= " - " . $response->json('error.message', 'Erro desconhecido');
                }
                throw new Exception($errorMsg);
            }

            $data = $response->json();

            if (!isset($data['routes'][0])) {
                throw new Exception('OpenRouteService não retornou rotas válidas');
            }

            $route = $data['routes'][0];
            $summary = $route['summary'];

            return [
                'distance' => $summary['distance'],
                'duration' => $summary['duration'],
                'geometry' => json_encode($route['geometry']),
                'instructions' => $this->formatOpenRouteInstructions($route['segments'][0]['steps'] ?? [])
            ];

        } catch (Exception $e) {
            Log::error($e);
            throw new Exception("Falha ao calcular rota via OpenRouteService: " . $e->getMessage());
        }
    }

    /**
     * Formata instruções do OSRM
     */
    private function formatOsrmInstructions(array $steps): ?string
    {
        if (empty($steps)) {
            return null;
        }

        $instructions = [];
        foreach ($steps as $step) {
            $instructions[] = [
                'instruction' => $step['maneuver']['type'] ?? 'continue',
                'name' => $step['name'] ?? '',
                'distance' => $step['distance'] ?? 0,
                'duration' => $step['duration'] ?? 0
            ];
        }

        return json_encode($instructions);
    }

    /**
     * Formata instruções do OpenRouteService
     */
    private function formatOpenRouteInstructions(array $steps): ?string
    {
        if (empty($steps)) {
            return null;
        }

        $instructions = [];
        foreach ($steps as $step) {
            $instructions[] = [
                'instruction' => $step['instruction'] ?? '',
                'name' => $step['name'] ?? '',
                'distance' => $step['distance'] ?? 0,
                'duration' => $step['duration'] ?? 0
            ];
        }

        return json_encode($instructions);
    }

    /**
     * Estima tempo de cálculo baseado no número de pontos
     */
    public function estimateCalculationTime(int $pointCount): int
    {
        $segmentCount = max(0, $pointCount - 1);

        if ($this->useExternalApi) {
            // API externa é mais lenta devido à latência de rede
            return max(5, $segmentCount * 2);
        } else {
            // OSRM local é mais rápido
            return max(2, $segmentCount * 1);
        }
    }

    /**
     * Obtém status do cálculo de uma rota
     */
    public function getCalculationStatus(Route $route): array
    {
        $status = [
            'status' => $route->calculation_status ?? 'not_started',
            'started_at' => $route->calculation_started_at,
            'completed_at' => $route->calculation_completed_at,
            'error' => $route->calculation_error,
            'api_mode' => $this->useExternalApi ? 'external' : 'osrm'
        ];

        if ($route->calculation_status === 'calculating') {
            $startedAt = $route->calculation_started_at;
            if ($startedAt) {
                $elapsed = now()->diffInSeconds($startedAt);
                $estimated = $this->estimateCalculationTime($route->points->count());

                $status['elapsed_seconds'] = $elapsed;
                $status['estimated_total_seconds'] = $estimated;
                $status['progress_percentage'] = min(95, ($elapsed / $estimated) * 100);
            }
        }

        return $status;
    }

    /**
     * Testa conectividade com o serviço configurado
     */
    public function testConnection(): array
    {
        $testCoords = [
            'from' => [-23.5505, -46.6333], // São Paulo
            'to' => [-22.9068, -43.1729]    // Rio de Janeiro
        ];

        try {
            if ($this->useExternalApi) {
                return $this->testExternalApiConnection($testCoords);
            } else {
                return $this->testOsrmConnection($testCoords);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'api_mode' => $this->useExternalApi ? 'external' : 'osrm'
            ];
        }
    }

    /**
     * Verifica se o serviço está pronto para uso
     */
    public function isServiceReady(): bool
    {
        try {
            $result = $this->testConnection();
            return $result['success'];
        } catch (Exception $e) {
            return false;
        }
    }

    private function testOsrmConnection(array $coords): array
    {
        $url = "{$this->osrmUrl}/route/v1/driving/{$coords['from'][1]},{$coords['from'][0]};{$coords['to'][1]},{$coords['to'][0]}";

        $start = microtime(true);
        $response = Http::timeout(10)->get($url);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        return [
            'success' => $response->successful(),
            'response_time_ms' => $responseTime,
            'api_mode' => 'osrm',
            'url' => $this->osrmUrl
        ];
    }

    private function testExternalApiConnection(array $coords): array
    {
        $url = "{$this->externalApiUrl}/v2/directions/driving-car";

        $payload = [
            'coordinates' => [
                [$coords['from'][1], $coords['from'][0]],
                [$coords['to'][1], $coords['to'][0]]
            ],
            'format' => 'json'
        ];

        $start = microtime(true);
        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => $this->apiKey])
            ->post($url, $payload);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        return [
            'success' => $response->successful(),
            'response_time_ms' => $responseTime,
            'api_mode' => 'external',
            'url' => $this->externalApiUrl,
            'has_api_key' => !empty($this->apiKey)
        ];
    }

    /**
     * Detecta se está rodando em container Docker
     */
    private function isRunningInDocker(): bool
    {
        return file_exists('/.dockerenv') ||
            env('DS_APP_HOST') === 'host.docker.internal' ||
            (strlen(gethostname()) === 12 && ctype_alnum(gethostname()));
    }
}

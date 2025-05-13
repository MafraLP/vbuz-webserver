<?php

namespace App\Http\Controllers;

use App\Models\Routes\Route;
use App\Models\Routes\RoutePoint;
use App\Models\Routes\RouteSegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class RouteController extends Controller
{
    /**
     * Exibe todas as rotas do usuário autenticado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $routes = Route::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'routes' => $routes
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar rotas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma rota específica
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $route = Route::query()->with(['points', 'segments'])->findOrFail($id);

            // Verificar se a rota pertence ao usuário autenticado ou é pública
            if (!$route->is_published && $route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta rota'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'route' => $route
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao buscar rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova rota
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'points' => 'sometimes|array',
                'points.*.name' => 'required|string|max:255',
                'points.*.description' => 'nullable|string',
                'points.*.latitude' => 'required|numeric|between:-90,90',
                'points.*.longitude' => 'required|numeric|between:-180,180',
            ]);

            // Criar a rota
            $route = new Route();
            $route->name = $validated['name'];
            $route->user_id = Auth::id();
            $route->total_distance = 0;
            $route->total_duration = 0;
            $route->is_published = false;
            $route->save();

            // Adicionar pontos à rota, se fornecidos
            if (isset($validated['points']) && count($validated['points']) > 0) {
                foreach ($validated['points'] as $index => $pointData) {
                    $point = new RoutePoint();
                    $point->route_id = $route->id;
                    $point->sequence = $index;
                    $point->name = $pointData['name'];
                    $point->description = $pointData['description'] ?? null;
                    $point->latitude = $pointData['latitude'];
                    $point->longitude = $pointData['longitude'];
                    $point->save();
                }

                // Se tiver mais de um ponto, calcular a rota
                if (count($validated['points']) > 1) {
                    $this->calculateRoute($route->id);
                }
            }

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => 'Rota criada com sucesso'
            ], 201);
        } catch (Exception $e) {
            Log::error('Erro ao criar rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma rota existente
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $route = Route::query()->findOrFail($id);

            // Verificar se a rota pertence ao usuário autenticado
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta rota'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'is_published' => 'sometimes|boolean',
                // Não permitir atualização direta de distance e duration - são calculados automaticamente
            ]);

            $route->fill($validated);
            $route->save();

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => 'Rota atualizada com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao atualizar rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove uma rota
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $route = Route::query()->findOrFail($id);

            // Verificar se a rota pertence ao usuário autenticado
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir esta rota'
                ], 403);
            }

            // Remover pontos e segmentos (deve acontecer automaticamente devido às chaves estrangeiras com onDelete cascade)
            // Mas vamos garantir que tudo seja excluído
            $route->points()->delete();
            $route->segments()->delete();
            $route->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rota removida com sucesso'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao remover rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula ou recalcula uma rota existente (utilizando OpenRoute Service ou outro serviço)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateRoute($id)
    {
        DB::beginTransaction();

        try {
            $route = Route::query()->with('points')->findOrFail($id);

            // Verificar se a rota pertence ao usuário autenticado
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta rota'
                ], 403);
            }

            // Verificar se a rota tem pelo menos 2 pontos
            if ($route->points->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'A rota precisa ter pelo menos 2 pontos para ser calculada'
                ], 400);
            }

            // Limpar os segmentos antigos
            $route->segments()->delete();

            // Ordenar os pontos por sequência
            $points = $route->points()->orderBy('sequence')->get();

            $totalDistance = 0;
            $totalDuration = 0;

            // Perfil para cálculo de rota (carro, a pé, bicicleta, etc.)
            $profile = 'driving-car'; // Valor padrão - pode ser parametrizado

            // Criação de segmentos entre pontos consecutivos
            for ($i = 0; $i < $points->count() - 1; $i++) {
                $startPoint = $points[$i];
                $endPoint = $points[$i + 1];

                // Criar chave de cache para esta combinação de pontos e perfil
                $cacheKey = md5(
                    $startPoint->latitude . ',' . $startPoint->longitude . '-' .
                    $endPoint->latitude . ',' . $endPoint->longitude . '-' .
                    $profile
                );

                // Verificar se já existe um segmento calculado para este par de pontos
                $existingSegment = RouteSegment::where('cache_key', $cacheKey)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($existingSegment) {
                    // Reutilizar o segmento existente
                    $segment = new RouteSegment();
                    $segment->route_id = $route->id;
                    $segment->sequence = $i;
                    $segment->start_point_id = $startPoint->id;
                    $segment->end_point_id = $endPoint->id;
                    $segment->distance = $existingSegment->distance;
                    $segment->duration = $existingSegment->duration;
                    $segment->geometry = $existingSegment->geometry;
                    $segment->encoded_polyline = $existingSegment->encoded_polyline;
                    $segment->profile = $profile;
                    $segment->cache_key = $cacheKey;
                    $segment->expires_at = $existingSegment->expires_at;
                    $segment->save();
                } else {
                    // Calcular o novo segmento utilizando um serviço externo (como o OpenRoute Service)
                    $segmentData = $this->callRoutingService(
                        $startPoint->latitude, $startPoint->longitude,
                        $endPoint->latitude, $endPoint->longitude,
                        $profile
                    );

                    $segment = new RouteSegment();
                    $segment->route_id = $route->id;
                    $segment->sequence = $i;
                    $segment->start_point_id = $startPoint->id;
                    $segment->end_point_id = $endPoint->id;
                    $segment->distance = $segmentData['distance']; // em metros
                    $segment->duration = $segmentData['duration']; // em segundos
                    $segment->geometry = $segmentData['geometry'] ?? null; // GeoJSON
                    $segment->encoded_polyline = $segmentData['encoded_polyline'] ?? null;
                    $segment->profile = $profile;
                    $segment->cache_key = $cacheKey;
                    $segment->expires_at = now()->addDays(30); // Expira em 30 dias
                    $segment->save();
                }

                $totalDistance += $segment->distance;
                $totalDuration += $segment->duration;
            }

            // Atualizar a rota com as novas métricas
            $route->total_distance = $totalDistance / 1000; // Converter para km
            $route->total_duration = $totalDuration / 60; // Converter para minutos
            $route->last_calculated_at = now();
            $route->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'route' => Route::with(['points', 'segments'])->find($id),
                'message' => 'Rota calculada com sucesso'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao calcular rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao calcular rota: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chama um serviço externo para calcular um segmento de rota
     *
     * @param float $startLat
     * @param float $startLon
     * @param float $endLat
     * @param float $endLon
     * @param string $profile
     * @return array
     */
    private function callRoutingService($startLat, $startLon, $endLat, $endLon, $profile)
    {
        // Configuração para OpenRoute Service
        $openRouteServiceApiKey = config('services.openrouteservice.api_key');
        $openRouteServiceUrl = 'https://api.openrouteservice.org/v2/directions/' . $profile;

        // Coordenadas no formato esperado pelo OpenRoute Service (longitude,latitude)
        $coordinates = [
            [$startLon, $startLat],
            [$endLon, $endLat]
        ];

        try {
            // Chamada para a API do OpenRoute Service
            $response = Http::withHeaders([
                'Authorization' => $openRouteServiceApiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($openRouteServiceUrl, [
                'coordinates' => $coordinates,
                'format' => 'geojson',
                'instructions' => false
            ]);

            if ($response->successful()) {
                $routeData = $response->json();

                // Extrair dados relevantes da resposta
                $feature = $routeData['features'][0] ?? null;
                if ($feature) {
                    $properties = $feature['properties'] ?? [];
                    $segments = $properties['segments'][0] ?? [];

                    return [
                        'distance' => $segments['distance'] ?? 0, // em metros
                        'duration' => $segments['duration'] ?? 0, // em segundos
                        'geometry' => $feature['geometry'] ?? null, // GeoJSON
                        'encoded_polyline' => $this->encodePolyline($feature['geometry']['coordinates'] ?? [])
                    ];
                }
            }

            // Em caso de falha, log da resposta para diagnóstico
            Log::warning('Falha na API do OpenRoute Service', [
                'response' => $response->json() ?? $response->body(),
                'status' => $response->status()
            ]);

            // Retornar dados mínimos para não quebrar o fluxo
            return [
                'distance' => $this->calculateHaversineDistance($startLat, $startLon, $endLat, $endLon),
                'duration' => 0,
                'geometry' => null,
                'encoded_polyline' => null
            ];
        } catch (Exception $e) {
            Log::error('Erro ao chamar o serviço de roteamento: ' . $e->getMessage());

            // Cálculo simplificado em caso de falha (distância em linha reta usando Haversine)
            return [
                'distance' => $this->calculateHaversineDistance($startLat, $startLon, $endLat, $endLon),
                'duration' => 0,
                'geometry' => null,
                'encoded_polyline' => null
            ];
        }
    }

    /**
     * Calcula a distância entre dois pontos usando a fórmula de Haversine (em metros)
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float
     */
    private function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Raio da Terra em metros

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

        return $earthRadius * $c; // Distância em metros
    }

    /**
     * Codifica um array de coordenadas em uma string polyline
     *
     * @param array $coordinates Array de coordenadas no formato [[lon1, lat1], [lon2, lat2], ...]
     * @return string
     */
    private function encodePolyline($coordinates)
    {
        // Implementação simplificada do algoritmo de codificação polyline do Google
        if (empty($coordinates)) {
            return '';
        }

        $points = [];
        // Converter coordenadas [lon, lat] para [lat, lon] para o formato do algoritmo
        foreach ($coordinates as $coord) {
            if (isset($coord[0]) && isset($coord[1])) {
                $points[] = [$coord[1], $coord[0]]; // [lat, lon]
            }
        }

        $polyline = '';
        $lastLat = 0;
        $lastLng = 0;

        foreach ($points as $point) {
            $lat = $point[0];
            $lng = $point[1];

            // Converter para inteiro (multiplicar por 1e5 para precisão de 5 casas decimais)
            $latE5 = round($lat * 1e5);
            $lngE5 = round($lng * 1e5);

            // Calcular delta em relação ao ponto anterior
            $deltaLat = $latE5 - $lastLat;
            $deltaLng = $lngE5 - $lastLng;

            // Atualizar últimos valores
            $lastLat = $latE5;
            $lastLng = $lngE5;

            // Codificar os deltas
            $polyline .= $this->encodeValue($deltaLat) . $this->encodeValue($deltaLng);
        }

        return $polyline;
    }

    /**
     * Codifica um valor para o formato polyline
     *
     * @param int $value
     * @return string
     */
    private function encodeValue($value)
    {
        // Deslocamento para a esquerda
        $value = $value < 0 ? ~($value << 1) : ($value << 1);

        $result = '';

        while ($value >= 0x20) {
            $result .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        $result .= chr($value + 63);
        return $result;
    }

    /**
     * Publica ou despublica uma rota
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePublish($id, Request $request)
    {
        try {
            $route = Route::query()->findOrFail($id);

            // Verificar se a rota pertence ao usuário autenticado
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta rota'
                ], 403);
            }

            $validated = $request->validate([
                'is_published' => 'required|boolean',
            ]);

            $route->is_published = $validated['is_published'];
            $route->save();

            $message = $route->is_published ? 'Rota publicada com sucesso' : 'Rota despublicada com sucesso';

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => $message
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao alterar status de publicação da rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar status de publicação da rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista as rotas públicas
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function publicRoutes(Request $request)
    {
        try {
            $query = Route::where('is_published', true);

            // Filtro por usuário
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Ordenação
            $query->orderBy('created_at', 'desc');

            // Paginação
            $perPage = $request->input('per_page', 15);
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'routes' => $routes
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar rotas públicas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas públicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca rotas próximas a um ponto geográfico
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nearby(Request $request)
    {
        try {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'sometimes|numeric|min:0.1|max:50', // raio em km
                'limit' => 'sometimes|integer|min:1|max:100',
            ]);

            $latitude = $validated['latitude'];
            $longitude = $validated['longitude'];
            $radius = $validated['radius'] ?? 5; // default 5km
            $limit = $validated['limit'] ?? 10; // default 10 rotas

            // Construir a query espacial usando PostGIS
            // Busca rotas que têm pelo menos um ponto dentro do raio especificado
            $nearbyRoutes = DB::select("
                SELECT DISTINCT r.id, r.name, r.user_id, r.total_distance, r.total_duration, r.is_published, r.created_at,
                    MIN(
                        ST_Distance(
                            ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography,
                            p.location::geography
                        )
                    ) as distance
                FROM routes r
                JOIN route_points p ON r.id = p.route_id
                WHERE r.is_published = true
                AND ST_DWithin(
                    ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography,
                    p.location::geography,
                    :radius_meters
                )
                GROUP BY r.id, r.name, r.user_id, r.total_distance, r.total_duration, r.is_published, r.created_at
                ORDER BY distance ASC
                LIMIT :limit
            ", [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_meters' => $radius * 1000, // converter km para metros
                'limit' => $limit
            ]);

            // Formatar os resultados
            $routeIds = collect($nearbyRoutes)->pluck('id')->toArray();
            $routes = Route::whereIn('id', $routeIds)
                ->with(['user:id,name', 'points' => function($query) {
                    $query->orderBy('sequence');
                }])
                ->get();

            // Adicionar a distância do ponto de busca para cada rota
            $routes = $routes->map(function($route) use ($nearbyRoutes) {
                $nearbyRoute = collect($nearbyRoutes)->firstWhere('id', $route->id);
                $route->distance_from_search = round($nearbyRoute->distance / 1000, 2); // em km
                return $route;
            })->sortBy('distance_from_search')->values();

            return response()->json([
                'success' => true,
                'routes' => $routes,
                'search_location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radius
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar rotas próximas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas próximas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Routes\Route;
use App\Models\Routes\RoutePoint;
use App\Models\Users\User;
use App\Jobs\CalculateRouteSegmentsJob;
use App\Services\RouteCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    protected RouteCalculationService $calculationService;

    public function __construct(RouteCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Exibe todas as rotas das instituições do usuário autenticado
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
        }

        try {
            $institutionIds = $user->getInstitutions()->pluck('id')->toArray();

            $routes = Route::whereIn('institution_id', $institutionIds)
                ->with(['points' => function($query) {
                    $query->orderBy('sequence');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'routes' => $routes,
                'user_institutions' => $institutionIds
            ]);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma rota específica
     */
    public function show($id)
    {
        try {
            $route = Route::query()->with(['points' => function($query) {
                $query->orderBy('sequence');
            }, 'segments' => function($query) {
                $query->orderBy('sequence');
            }])->findOrFail($id);

            $user = Auth::user();

            // Verificar permissões
            if (!$this->userHasAccessToRoute($user, $route)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta rota'
                ], 403);
            }

            // Se a rota não tem segmentos calculados, calcular automaticamente
            if ($route->segments->count() === 0 && $route->points->count() >= 2) {
                                $this->startRouteCalculation($route);
            }

            return response()->json([
                'success' => true,
                'route' => $route
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'buscar rota');
        }
    }

    /**
     * Cria uma nova rota
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'institution_id' => 'required|exists:institutions,id',
                'points' => 'sometimes|array',
                'points.*.name' => 'required|string|max:255',
                'points.*.description' => 'nullable|string',
                'points.*.latitude' => 'required|numeric|between:-90,90',
                'points.*.longitude' => 'required|numeric|between:-180,180',
            ]);

            $user = Auth::user();

            if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar rotas nesta instituição'
                ], 403);
            }

            DB::beginTransaction();

            $route = $this->createRoute($validated);

            if (isset($validated['points']) && count($validated['points']) > 0) {
                $this->createRoutePoints($route, $validated['points']);

                if (count($validated['points']) >= 2) {
                                        $this->startRouteCalculation($route);
                }
            }

            DB::commit();

            $route = $this->loadRouteWithRelations($route->id);

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => 'Rota criada com sucesso'
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao criar rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula ou recalcula uma rota existente
     */
    public function calculateRoute($id)
    {
        try {
            $route = Route::query()->with('points')->findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyRoute($user, $route)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para calcular esta rota'
                ], 403);
            }

            if ($route->points->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'A rota precisa ter pelo menos 2 pontos para ser calculada'
                ], 400);
            }

                        $this->startRouteCalculation($route);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo da rota iniciado. Use /api/routes/{id}/status para acompanhar o progresso.',
                'status' => 'calculating',
                'estimated_time_seconds' => $this->calculationService->estimateCalculationTime($route->points->count())
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'calcular rota');
        }
    }

    /**
     * Verifica o status do cálculo de uma rota
     */
    public function getCalculationStatus($id)
    {
        try {
            $route = Route::query()->findOrFail($id);
            $status = $this->calculationService->getCalculationStatus($route);

            // Se concluído, retornar dados da rota
            if ($route->calculation_status === 'completed') {
                $route->load(['points' => function($query) {
                    $query->orderBy('sequence');
                }, 'segments' => function($query) {
                    $query->orderBy('sequence');
                }]);

                $status['route'] = $route;
            }

            return response()->json([
                'success' => true,
                'status' => $status
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma rota existente
     */
    public function update(Request $request, $id)
    {
        try {
            $route = Route::query()->findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyRoute($user, $route)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta rota'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'is_published' => 'sometimes|boolean',
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            if (isset($validated['institution_id']) && $validated['institution_id'] !== $route->institution_id) {
                if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para mover a rota para esta instituição'
                    ], 403);
                }
            }

            $route->fill($validated);
            $route->save();

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => 'Rota atualizada com sucesso'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'atualizar rota');
        }
    }

    /**
     * Remove uma rota
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $route = Route::query()->findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyRoute($user, $route)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir esta rota'
                ], 403);
            }

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
            return $this->handleException($e, 'remover rota');
        }
    }

    /**
     * Publica ou despublica uma rota
     */
    public function togglePublish($id, Request $request)
    {
        try {
            $route = Route::query()->findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyRoute($user, $route)) {
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
            return $this->handleException($e, 'alterar status de publicação da rota');
        }
    }

    /**
     * Lista as rotas públicas
     */
    public function publicRoutes(Request $request)
    {
        try {
            $query = Route::where('is_published', true);

            if ($request->has('institution_id')) {
                $query->where('institution_id', $request->institution_id);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->input('per_page', 15);
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'routes' => $routes
            ]);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas públicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca rotas próximas a um ponto geográfico
     */
    public function nearby(Request $request)
    {
        try {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'sometimes|numeric|min:0.1|max:50',
                'limit' => 'sometimes|integer|min:1|max:100',
            ]);

            $latitude = $validated['latitude'];
            $longitude = $validated['longitude'];
            $radius = $validated['radius'] ?? 5;
            $limit = $validated['limit'] ?? 10;

            // Query espacial usando PostGIS
            $nearbyRoutes = DB::select("
                SELECT DISTINCT r.id, r.name, r.institution_id, r.total_distance, r.total_duration, r.is_published, r.created_at,
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
                GROUP BY r.id, r.name, r.institution_id, r.total_distance, r.total_duration, r.is_published, r.created_at
                ORDER BY distance ASC
                LIMIT :limit
            ", [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_meters' => $radius * 1000,
                'limit' => $limit
            ]);

            $routeIds = collect($nearbyRoutes)->pluck('id')->toArray();
            $routes = Route::whereIn('id', $routeIds)
                ->with(['points' => function($query) {
                    $query->orderBy('sequence');
                }])
                ->get();

            $routes = $routes->map(function($route) use ($nearbyRoutes) {
                $nearbyRoute = collect($nearbyRoutes)->firstWhere('id', $route->id);
                $route->distance_from_search = round($nearbyRoute->distance / 1000, 2);
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
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas próximas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint específico para calcular/recalcular uma rota
     */
    public function recalculate($id)
    {
        return $this->calculateRoute($id);
    }

    // === MÉTODOS PRIVADOS DE AUXÍLIO ===

    /**
     * Verifica se o usuário tem acesso à rota
     */
    private function userHasAccessToRoute(User $user, Route $route): bool
    {
        if ($route->is_published) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();
        return in_array($route->institution_id, $userInstitutionIds);
    }

    /**
     * Verifica se o usuário pode modificar a rota
     */
    private function userCanModifyRoute(User $user, Route $route): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();
        return in_array($route->institution_id, $userInstitutionIds);
    }

    /**
     * Verifica se o usuário pode acessar a instituição
     */
    private function userCanAccessInstitution(User $user, int $institutionId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();
        return in_array($institutionId, $userInstitutionIds);
    }

    /**
     * Inicia o cálculo da rota
     */
    private function startRouteCalculation(Route $route): void
    {
        $route->update([
            'calculation_status' => 'calculating',
            'calculation_started_at' => now()
        ]);

        CalculateRouteSegmentsJob::dispatch($route);
    }

    /**
     * Cria uma nova rota
     */
    private function createRoute(array $validated): Route
    {
        $route = new Route();
        $route->name = $validated['name'];
        $route->institution_id = $validated['institution_id'];
        $route->total_distance = 0;
        $route->total_duration = 0;
        $route->is_published = false;
        $route->save();

        return $route;
    }

    /**
     * Cria pontos da rota
     */
    private function createRoutePoints(Route $route, array $points): void
    {
        foreach ($points as $index => $pointData) {
            $point = new RoutePoint();
            $point->route_id = $route->id;
            $point->sequence = $index;
            $point->name = $pointData['name'];
            $point->description = $pointData['description'] ?? null;
            $point->latitude = $pointData['latitude'];
            $point->longitude = $pointData['longitude'];
            $point->save();
        }
    }

    /**
     * Carrega rota com relacionamentos
     */
    private function loadRouteWithRelations(int $routeId): Route
    {
        return Route::with([
            'points' => function($query) {
                $query->orderBy('sequence');
            },
            'segments' => function($query) {
                $query->orderBy('sequence');
            }
        ])->find($routeId);
    }

    /**
     * Trata exceções de forma padronizada
     */
    private function handleException(Exception $e, string $action): \Illuminate\Http\JsonResponse
    {
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Rota não encontrada'
            ], 404);
        }

                return response()->json([
            'success' => false,
            'message' => "Erro ao {$action}",
            'error' => $e->getMessage()
        ], 500);
    }

    public function getInstitutionRoutes($institutionId, Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            // *** VERIFICAR PERMISSÃO DE ACESSO À INSTITUIÇÃO ***
            if (!$this->userCanAccessInstitution($user, $institutionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar rotas desta instituição'
                ], 403);
            }

            // *** VALIDAR PARÂMETROS OPCIONAIS ***
            $validated = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:calculating,completed,error,failed',
                'published_only' => 'sometimes|boolean',
                'order_by' => 'sometimes|in:created_at,updated_at,name,total_distance,total_duration',
                'order_direction' => 'sometimes|in:asc,desc'
            ]);

            // *** CONSTRUIR QUERY ***
            $query = Route::where('institution_id', $institutionId);

            // Filtro por texto (busca no nome)
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where('name', 'ILIKE', "%{$search}%");
            }

            // Filtro por status de cálculo
            if (!empty($validated['status'])) {
                $query->where('calculation_status', $validated['status']);
            }

            // Filtro por rotas publicadas apenas
            if (!empty($validated['published_only'])) {
                $query->where('is_published', true);
            }

            // *** ORDENAÇÃO ***
            $orderBy = $validated['order_by'] ?? 'created_at';
            $orderDirection = $validated['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // *** INCLUIR RELACIONAMENTOS ***
            $query->with([
                'points' => function($pointQuery) {
                    $pointQuery->orderBy('sequence')->select(['id', 'route_id', 'sequence', 'name', 'latitude', 'longitude', 'type']);
                },
                'segments' => function($segmentQuery) {
                    $segmentQuery->orderBy('sequence')->select(['id', 'route_id', 'sequence', 'distance', 'duration']);
                }
            ]);

            // *** PAGINAR RESULTADOS ***
            $perPage = $validated['per_page'] ?? 15;
            $routes = $query->paginate($perPage);

            // *** ADICIONAR ESTATÍSTICAS DA INSTITUIÇÃO ***
            $institutionStats = $this->getInstitutionRouteStats($institutionId);

            return response()->json([
                'success' => true,
                'routes' => $routes,
                'institution_id' => (int) $institutionId,
                'stats' => $institutionStats,
                'filters_applied' => [
                    'search' => $validated['search'] ?? null,
                    'status' => $validated['status'] ?? null,
                    'published_only' => $validated['published_only'] ?? false,
                    'order_by' => $orderBy,
                    'order_direction' => $orderDirection
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rotas da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca estatísticas de rotas de uma instituição
     */
    public function getInstitutionRouteStats($institutionId)
    {
        try {
            $user = Auth::user();

            if (!$user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            // *** VERIFICAR PERMISSÃO ***
            if (!$this->userCanAccessInstitution($user, $institutionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar estatísticas desta instituição'
                ], 403);
            }

            // *** CALCULAR ESTATÍSTICAS ***
            //$stats = $this->calculateInstitutionStats($institutionId);

            return response()->json([
                'success' => true,
                'institution_id' => (int) $institutionId,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar estatísticas da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

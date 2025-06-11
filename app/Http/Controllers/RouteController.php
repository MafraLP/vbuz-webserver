<?php

namespace App\Http\Controllers;

use App\Models\Point;
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
use Illuminate\Validation\ValidationException;

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
            $institutionIds = $user->institutions()->pluck('institutions.id')->toArray();

            $routes = Route::whereIn('institution_id', $institutionIds)
                ->with([
                    'points' => function($query) {
                        $query->orderBy('sequence');
                    },
                    'permissions',
                    'institution'
                ])
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
            $route = Route::query()->with([
                'points' => function($query) {
                    $query->orderBy('sequence');
                },
                'segments' => function($query) {
                    $query->orderBy('sequence');
                },
                'permissions',
                'institution'
            ])->findOrFail($id);

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
     * Criar ou encontrar pontos e associar à rota
     */
    private function createRoutePoints($route, $pointsData)
    {

        foreach ($pointsData as $index => $pointData) {

            try {
                // 1. Tentar encontrar ponto existente por coordenadas próximas
                $existingPoint = $this->findExistingPoint(
                    $pointData['latitude'],
                    $pointData['longitude'],
                    $route->institution_id,
                    $pointData['name'] ?? null
                );

                if ($existingPoint) {
                    $point = $existingPoint;

                } else {
                    // 2. Criar novo ponto
                    $point = $this->createNewPoint($pointData, $route->institution_id);

                }

                // 3. Verificar se o ponto foi criado/encontrado corretamente
                if (!$point || !$point->id) {
                    throw new \Exception("Falha ao criar/encontrar ponto para índice {$index}");
                }

                // 4. Associar ponto à rota via route_points
                $routePoint = $this->attachPointToRoute($route, $point, $pointData, $index);

            } catch (\Exception $e) {

                throw $e;
            }
        }

        // 5. Atualizar o último ponto como 'end'
        $this->updateLastPointAsEnd($route);

    }


    /**
     * Encontrar ponto existente baseado em coordenadas e nome
     */
    private function findExistingPoint($latitude, $longitude, $institutionId, $name = null)
    {

        try {
            // ⭐ USAR O MÉTODO findNearby DO MODEL POINT
            $nearbyPoints = Point::findNearby(
                $latitude,
                $longitude,
                50, // 50 metros de tolerância
                10, // máximo 10 resultados
                [$institutionId] // filtrar por instituição
            );

            if ($nearbyPoints->isEmpty()) {

                return null;
            }

            // Se o nome foi fornecido, tentar encontrar por nome
            if ($name) {
                // Busca exata por nome
                $exactMatch = $nearbyPoints->first(function ($point) use ($name) {
                    return strcasecmp($point->name, $name) === 0;
                });

                if ($exactMatch) {

                    return $exactMatch;
                }

                // Busca por nome similar
                $similarMatch = $nearbyPoints->first(function ($point) use ($name) {
                    return stripos($point->name, $name) !== false ||
                        stripos($name, $point->name) !== false;
                });

                if ($similarMatch) {

                    return $similarMatch;
                }
            }

            // Pegar o ponto mais próximo
            $closestPoint = $nearbyPoints->first();

            return $closestPoint;

        } catch (\Exception $e) {

            return null; // Em caso de erro, criar novo ponto
        }
    }



    /**
     * Criar novo ponto
     */
    private function createNewPoint($pointData, $institutionId)
    {

        try {
            $point = Point::create([
                'name' => $pointData['name'],
                'description' => $pointData['description'] ?? null,
                'latitude' => $pointData['latitude'],
                'longitude' => $pointData['longitude'],
                'institution_id' => $institutionId,
                'type' => $this->determinePointType($pointData),
                'is_active' => true,
                'notes' => $pointData['notes'] ?? null,
                // Campos adicionais com valores padrão
                'has_shelter' => $pointData['has_shelter'] ?? false,
                'is_accessible' => $pointData['is_accessible'] ?? false,
                'has_lighting' => $pointData['has_lighting'] ?? false,
                'has_security' => $pointData['has_security'] ?? false,
                'capacity' => $pointData['capacity'] ?? null,
                'operating_hours' => $pointData['operating_hours'] ?? null,
                'route_codes' => $pointData['route_codes'] ?? null,
            ]);

            // Verificar se foi criado corretamente
            if (!$point || !$point->id) {
                throw new \Exception("Falha ao criar ponto no banco de dados");
            }

            return $point;

        } catch (\Exception $e) {

            throw $e;
        }
    }

    private function determinePointType($pointData)
    {
        // Verificar se o tipo foi especificado explicitamente
        if (isset($pointData['type']) && !empty($pointData['type'])) {
            return $pointData['type'];
        }

        // Inferir tipo baseado no nome (opcional)
        $name = strtolower($pointData['name'] ?? '');

        // Terminais e estações
        if (str_contains($name, 'terminal')) return 'terminal';
        if (str_contains($name, 'estação') || str_contains($name, 'station')) return 'terminal';
        if (str_contains($name, 'rodoviária') || str_contains($name, 'rodoviario')) return 'terminal';

        // Pontos educacionais
        if (str_contains($name, 'escola') || str_contains($name, 'school')) return 'school_stop';
        if (str_contains($name, 'universidade') || str_contains($name, 'university')) return 'university_stop';
        if (str_contains($name, 'faculdade') || str_contains($name, 'college')) return 'university_stop';
        if (str_contains($name, 'campus')) return 'campus_entrance';
        if (str_contains($name, 'residência estudantil') || str_contains($name, 'dormitory')) return 'dormitory';

        // Pontos de saúde
        if (str_contains($name, 'hospital')) return 'hospital';
        if (str_contains($name, 'posto de saúde') || str_contains($name, 'health center')) return 'health_center';
        if (str_contains($name, 'pronto socorro') || str_contains($name, 'emergency')) return 'emergency';
        if (str_contains($name, 'upa') || str_contains($name, 'unidade pronto atendimento')) return 'emergency';

        // Pontos comerciais e serviços
        if (str_contains($name, 'shopping')) return 'shopping_center';
        if (str_contains($name, 'mercado') || str_contains($name, 'supermercado')) return 'market';
        if (str_contains($name, 'feira')) return 'market';
        if (str_contains($name, 'banco') || str_contains($name, 'bank')) return 'bank';
        if (str_contains($name, 'prefeitura') || str_contains($name, 'city hall')) return 'government_office';
        if (str_contains($name, 'câmara') || str_contains($name, 'fórum')) return 'government_office';

        // Pontos de transporte e integração
        if (str_contains($name, 'aeroporto') || str_contains($name, 'airport')) return 'airport';
        if (str_contains($name, 'metro') || str_contains($name, 'metrô')) return 'metro_station';
        if (str_contains($name, 'trem') || str_contains($name, 'train')) return 'train_station';
        if (str_contains($name, 'garagem') || str_contains($name, 'depot')) return 'bus_depot';

        // Pontos residenciais
        if (str_contains($name, 'conjunto habitacional') || str_contains($name, 'cohab')) return 'residential_complex';
        if (str_contains($name, 'condomínio') || str_contains($name, 'residencial')) return 'residential_complex';
        if (str_contains($name, 'bairro') || str_contains($name, 'neighborhood')) return 'neighborhood_center';

        // Pontos de lazer e cultura
        if (str_contains($name, 'parque') || str_contains($name, 'park')) return 'park';
        if (str_contains($name, 'ginásio') || str_contains($name, 'sports center')) return 'sports_center';
        if (str_contains($name, 'estádio') || str_contains($name, 'stadium')) return 'sports_center';
        if (str_contains($name, 'centro cultural') || str_contains($name, 'cultural center')) return 'cultural_center';
        if (str_contains($name, 'museu') || str_contains($name, 'museum')) return 'museum';
        if (str_contains($name, 'biblioteca') || str_contains($name, 'library')) return 'library';
        if (str_contains($name, 'teatro') || str_contains($name, 'theater')) return 'cultural_center';

        // Pontos especiais baseados em características
        if (str_contains($name, 'expresso') || str_contains($name, 'express')) return 'express_stop';
        if (str_contains($name, 'acessível') || str_contains($name, 'accessible')) return 'accessible_stop';
        if (str_contains($name, 'temporário') || str_contains($name, 'temporary')) return 'temporary_stop';
        if (str_contains($name, 'sob demanda') || str_contains($name, 'request')) return 'request_stop';

        // Tipo padrão
        return 'stop';
    }

    /**
     * Associar ponto à rota via tabela intermediária
     */
    private function attachPointToRoute($route, $point, $pointData, $sequence)
    {
        // Verificar se os parâmetros são válidos
        if (!$route || !$route->id) {
            throw new \Exception("Rota inválida para associação");
        }

        if (!$point || !$point->id) {
            throw new \Exception("Ponto inválido para associação");
        }

        try {
            // Verificar se já existe associação para evitar duplicatas
            $existingAssociation = RoutePoint::where('route_id', $route->id)
                ->where('point_id', $point->id)
                ->where('sequence', $pointData['sequence'] ?? $sequence)
                ->first();

            if ($existingAssociation) {

                return $existingAssociation;
            }

            // ⭐ USAR O MÉTODO createWithValidation para garantir validação
            $routePointData = [
                'route_id' => $route->id,
                'point_id' => $point->id,
                'sequence' => $pointData['sequence'] ?? $sequence,
                'type' => $this->determineRoutePointType($sequence, $pointData),
                'stop_duration' => $pointData['stop_duration'] ?? null,
                'is_optional' => $pointData['is_optional'] ?? false,
                'route_specific_notes' => $pointData['route_specific_notes'] ?? null,
                'arrival_time' => $pointData['arrival_time'] ?? null,
                'departure_time' => $pointData['departure_time'] ?? null,
            ];

            $routePoint = RoutePoint::createWithValidation($routePointData);

            return $routePoint;

        } catch (\Exception $e) {

            throw $e;
        }
    }

    private function getAllPointsData($route)
    {
        // Este método é chamado durante a criação, então não temos todos os pontos ainda
        // Retornamos null para usar a lógica de atualização posterior
        return null;
    }

    /**
     * Determinar o tipo do ponto baseado nos dados
     */
    private function determineRoutePointType($sequence, $pointData)
    {
        // Verificar se foi especificado explicitamente
        if (isset($pointData['route_point_type'])) {
            return $pointData['route_point_type'];
        }

        // Inferir baseado na sequência
        if ($sequence === 0) {
            return 'start';
        }

        // Por padrão é intermediário (será atualizado depois via updatePointTypes)
        return 'intermediate';
    }

    /**
     * Atualizar o último ponto como 'end'
     */
    private function updateLastPointAsEnd($route)
    {
        try {

            // ⭐ USAR O MÉTODO DO MODEL ROUTE
            $route->updatePointTypes();

        } catch (\Exception $e) {

            // Não lançar exceção pois não é crítico
        }
    }


    /**
     * Carregar rota com todas as relações
     */
    private function loadRouteWithRelations($routeId)
    {
        try {
            $route = Route::with([
                'institution',
                'permissions',
                'routePoints' => function ($query) {
                    $query->orderBy('sequence');
                },
                'routePoints.point', // ⭐ CARREGAR O PONTO ASSOCIADO
                'segments' => function ($query) {
                    $query->orderBy('sequence');
                }
            ])->find($routeId);

            if (!$route) {
                throw new \Exception("Rota {$routeId} não encontrada");
            }

            return $route;

        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Método store corrigido
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'institution_id' => 'required|exists:institutions,id',
                'schedule_type' => 'required|in:daily,weekly,monthly,custom',
                'schedule_data' => 'required|array',
                'points' => 'required|array|min:2',
                'points.*.name' => 'required|string|max:255',
                'points.*.latitude' => 'required|numeric|between:-90,90',
                'points.*.longitude' => 'required|numeric|between:-180,180',
            ]);

            // REMOVER TRANSAÇÃO PARA IDENTIFICAR O ERRO EXATO
            // DB::beginTransaction();

            try {
                // 1. Criar rota SEM transação primeiro
                $route = Route::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'institution_id' => $validated['institution_id'],
                    'schedule_type' => $validated['schedule_type'],
                    'schedule_data' => $validated['schedule_data'],
                    'is_public' => true,
                    'calculation_status' => 'not_started',
                    'created_by' => Auth::id()
                ]);

                // 2. Criar APENAS UM ponto para testar
                $firstPoint = $validated['points'][0];

                $point = Point::create([
                    'name' => $firstPoint['name'],
                    'description' => $firstPoint['description'] ?? null,
                    'latitude' => $firstPoint['latitude'],
                    'longitude' => $firstPoint['longitude'],
                    'institution_id' => $validated['institution_id'],
                    'type' => 'stop',
                    'is_active' => true,
                ]);

                // 3. Criar RoutePoint SEM validações extras
                $routePoint = RoutePoint::create([
                    'route_id' => $route->id,
                    'point_id' => $point->id,
                    'sequence' => 0,
                    'type' => 'start',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Teste de criação bem-sucedido',
                    'data' => [
                        'route_id' => $route->id,
                        'point_id' => $point->id,
                        'route_point_id' => $routePoint->id
                    ]
                ], 201);

            } catch (\Exception $e) {
                // Capturar erro específico sem rollback
                return response()->json([
                    'success' => false,
                    'message' => 'Erro específico identificado',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], 500);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
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
                $route->load([
                    'points' => function($query) {
                        $query->orderBy('sequence');
                    },
                    'segments' => function($query) {
                        $query->orderBy('sequence');
                    },
                    'permissions',
                    'institution'
                ]);

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
                'description' => 'sometimes|nullable|string',
                'is_published' => 'sometimes|boolean',
                'is_public' => 'sometimes|boolean',
                'institution_id' => 'sometimes|exists:institutions,id',
                'schedule_type' => 'sometimes|in:daily,weekly,monthly,custom',
                'schedule_data' => 'sometimes|array',
                'permissions' => 'sometimes|array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            if (isset($validated['institution_id']) && $validated['institution_id'] !== $route->institution_id) {
                if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para mover a rota para esta instituição'
                    ], 403);
                }
            }

            DB::beginTransaction();

            $route->fill($validated);
            $route->save();

            // Atualizar permissões se fornecidas
            if (isset($validated['permissions'])) {
                $route->permissions()->sync($validated['permissions']);
            }

            DB::commit();

            $route = $this->loadRouteWithRelations($route->id);

            return response()->json([
                'success' => true,
                'route' => $route,
                'message' => 'Rota atualizada com sucesso'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
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

            // Remover relacionamentos
            $route->permissions()->detach();
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
            $query = Route::where('is_published', true)
                ->where('is_public', true);

            if ($request->has('institution_id')) {
                $query->where('institution_id', $request->institution_id);
            }

            $query->with([
                'points' => function($q) {
                    $q->orderBy('sequence');
                },
                'permissions',
                'institution'
            ])->orderBy('created_at', 'desc');

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
                SELECT DISTINCT r.id, r.name, r.institution_id, r.total_distance, r.total_duration, r.is_published, r.is_public, r.created_at,
                    MIN(
                        ST_Distance(
                            ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography,
                            p.location::geography
                        )
                    ) as distance
                FROM routes r
                JOIN route_points p ON r.id = p.route_id
                WHERE r.is_published = true
                AND r.is_public = true
                AND ST_DWithin(
                    ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography,
                    p.location::geography,
                    :radius_meters
                )
                GROUP BY r.id, r.name, r.institution_id, r.total_distance, r.total_duration, r.is_published, r.is_public, r.created_at
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
                ->with([
                    'points' => function($query) {
                        $query->orderBy('sequence');
                    },
                    'permissions',
                    'institution'
                ])
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

            if (!$this->userCanAccessInstitution($user, $institutionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar rotas desta instituição'
                ], 403);
            }

            $validated = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:calculating,completed,error,failed',
                'published_only' => 'sometimes|boolean',
                'public_only' => 'sometimes|boolean',
                'order_by' => 'sometimes|in:created_at,updated_at,name,total_distance,total_duration',
                'order_direction' => 'sometimes|in:asc,desc'
            ]);

            $query = Route::where('institution_id', $institutionId);

            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where('name', 'ILIKE', "%{$search}%");
            }

            if (!empty($validated['status'])) {
                $query->where('calculation_status', $validated['status']);
            }

            if (!empty($validated['published_only'])) {
                $query->where('is_published', true);
            }

            if (!empty($validated['public_only'])) {
                $query->where('is_public', true);
            }

            $orderBy = $validated['order_by'] ?? 'created_at';
            $orderDirection = $validated['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            $query->with([
                'points' => function($pointQuery) {
                    $pointQuery->orderBy('sequence')->select(['id', 'route_id', 'sequence', 'name', 'latitude', 'longitude', 'type']);
                },
                'segments' => function($segmentQuery) {
                    $segmentQuery->orderBy('sequence')->select(['id', 'route_id', 'sequence', 'distance', 'duration']);
                },
                'permissions',
                'institution'
            ]);

            $perPage = $validated['per_page'] ?? 15;
            $routes = $query->paginate($perPage);

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
                    'public_only' => $validated['public_only'] ?? false,
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

            if (!$this->userCanAccessInstitution($user, $institutionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar estatísticas desta instituição'
                ], 403);
            }

            $stats = [
                'total_routes' => Route::where('institution_id', $institutionId)->count(),
                'published_routes' => Route::where('institution_id', $institutionId)->where('is_published', true)->count(),
                'public_routes' => Route::where('institution_id', $institutionId)->where('is_public', true)->count(),
                'total_distance' => Route::where('institution_id', $institutionId)->sum('total_distance'),
                'average_duration' => Route::where('institution_id', $institutionId)->avg('total_duration'),
            ];

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

    // === MÉTODOS PRIVADOS DE AUXÍLIO ===

    /**
     * Verifica se o usuário tem acesso à rota
     */
    private function userHasAccessToRoute(User $user, Route $route): bool
    {
        // Rotas públicas são acessíveis a todos
        if ($route->is_published && $route->is_public) {
            return true;
        }

        // Admins têm acesso a tudo
        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->institutions()->pluck('institutions.id')->toArray();

        // Verificar se o usuário pertence à instituição da rota
        if (!in_array($route->institution_id, $userInstitutionIds)) {
            return false;
        }

        // Se a rota tem permissões específicas, verificar se o usuário tem essas permissões
        if ($route->permissions->count() > 0) {
            $userPermissionIds = $user->permissions()->pluck('permissions.id')->toArray();
            $routePermissionIds = $route->permissions->pluck('id')->toArray();

            return !empty(array_intersect($userPermissionIds, $routePermissionIds));
        }

        return true;
    }

    /**
     * Verifica se o usuário pode modificar a rota
     */
    private function userCanModifyRoute(User $user, Route $route): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->institutions()->pluck('institutions.id')->toArray();
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

        $userInstitutionIds = $user->institutions()->pluck('institutions.id')->toArray();
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
        $route->description = $validated['description'] ?? null;
        $route->institution_id = $validated['institution_id'];
        $route->schedule_type = $validated['schedule_type'];
        $route->schedule_data = json_encode($validated['schedule_data']);
        $route->is_public = $validated['is_public'] ?? false;
        $route->total_distance = 0;
        $route->total_duration = 0;
        $route->is_published = false;
        $route->save();

        return $route;
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
}

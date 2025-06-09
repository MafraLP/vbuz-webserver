<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Point;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Exception;

class PointController extends Controller
{
    /**
     * Obtém todos os pontos acessíveis a uma instituição, incluindo os pontos de suas instituições pais
     *
     * @param int $institutionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstitutionPoints($institutionId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);

            // Obter todas as instituições ascendentes (pais, avós, etc.)
            $ancestorIds = $this->getAncestorInstitutionIds($institution);

            // Lista de IDs de instituições a serem consideradas (a própria instituição + ancestrais)
            $institutionIds = array_merge([$institutionId], $ancestorIds);

            // Buscar pontos de todas as instituições relevantes
            $points = Point::whereIn('institution_id', $institutionIds)
                ->where('is_active', true)
                ->get();

            // Adicionar informações de uso dos pontos (quantas rotas usam cada ponto)
            $pointsWithUsage = $points->map(function ($point) {
                $routeCount = $this->getRouteCountForPoint($point->id);
                $point->route_count = $routeCount;
                return $point;
            });

            return response()->json([
                'success' => true,
                'points' => $pointsWithUsage,
                'institution' => [
                    'id' => $institution->id,
                    'name' => $institution->name,
                    'type' => $institution->type,
                    'has_parent' => !empty($ancestorIds),
                    'parent_count' => count($ancestorIds)
                ]
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição não encontrada'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar pontos da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém todos os IDs de instituições ancestrais (pais, avós, etc.)
     *
     * @param Institution $institution
     * @return array
     */
    private function getAncestorInstitutionIds(Institution $institution)
    {
        $ancestorIds = [];
        $currentInstitution = $institution;

        // Seguir a cadeia de pais até chegar à raiz
        while ($currentInstitution->parent_id) {
            $parent = Institution::find($currentInstitution->parent_id);
            if ($parent) {
                $ancestorIds[] = $parent->id;
                $currentInstitution = $parent;
            } else {
                break; // Não foi possível encontrar o pai, quebrar o loop
            }
        }

        return $ancestorIds;
    }

    /**
     * Obtém o número de rotas que passam por um ponto específico
     *
     * @param int $pointId
     * @return int
     */
    private function getRouteCountForPoint($pointId)
    {
        // No sistema real, isso seria uma contagem de rotas que usam o ponto
        // Por agora, retornamos um valor aleatório para demonstração
        // Em um sistema real, você implementaria algo como:
        /*
        return DB::table('route_points')
            ->where('point_id', $pointId)
            ->distinct('route_id')
            ->count('route_id');
        */

        // Valor de demonstração
        return rand(0, 10);
    }

    /**
     * Exibe um ponto específico
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $point = Point::query()->findOrFail($id);

            // Adicionar informação sobre uso do ponto (quantas rotas o utilizam)
            $point->route_count = $this->getRouteCountForPoint($point->id);

            return response()->json([
                'success' => true,
                'point' => $point
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ponto não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Armazena um novo ponto
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:stop,terminal,landmark,connection',
                'description' => 'nullable|string',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'institution_id' => 'required|exists:institutions,id',
                'is_active' => 'boolean',
                'notes' => 'nullable|string',
            ]);

            // Verificar se o usuário tem permissão para criar pontos para esta instituição
            $hasPermission = $this->userHasPermissionForInstitution($validated['institution_id']);
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar pontos para esta instituição'
                ], 403);
            }

            $point = new Point();
            $point->fill($validated);
            $point->save();

            return response()->json([
                'success' => true,
                'point' => $point,
                'message' => 'Ponto criado com sucesso'
            ], 201);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao criar ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um ponto existente
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $point = Point::query()->findOrFail($id);

            // Verificar se o usuário tem permissão para atualizar este ponto
            $hasPermission = $this->userHasPermissionForInstitution($point->institution_id);
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para atualizar este ponto'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|string|in:stop,terminal,landmark,connection',
                'description' => 'nullable|string',
                'latitude' => 'sometimes|required|numeric',
                'longitude' => 'sometimes|required|numeric',
                'is_active' => 'boolean',
                'notes' => 'nullable|string',
            ]);

            // Não permitir alterar a instituição de um ponto existente
            unset($validated['institution_id']);

            $point->fill($validated);
            $point->save();

            return response()->json([
                'success' => true,
                'point' => $point,
                'message' => 'Ponto atualizado com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ponto não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um ponto
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $point = Point::query()->findOrFail($id);

            // Verificar se o usuário tem permissão para remover este ponto
            $hasPermission = $this->userHasPermissionForInstitution($point->institution_id);
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para remover este ponto'
                ], 403);
            }

            // Verificar se o ponto está sendo usado em alguma rota
            $routeCount = $this->getRouteCountForPoint($point->id);
            if ($routeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este ponto está sendo usado em ' . $routeCount . ' rota(s) e não pode ser removido',
                    'route_count' => $routeCount
                ], 422);
            }

            $point->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ponto removido com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ponto não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao remover ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se o usuário atual tem permissão para modificar pontos de uma instituição
     *
     * @param int $institutionId
     * @return bool
     */
    private function userHasPermissionForInstitution($institutionId)
    {
        $user = Auth::user();

        // Se o usuário for admin, tem permissão
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Verificar se o usuário está associado à instituição
        // Método temporário para demonstração - ajuste conforme sua lógica de autorização
        if (method_exists($user, 'getInstitutions')) {
            $institutions = $user->getInstitutions();
            $institutionIds = $institutions->pluck('id')->toArray();
            return in_array($institutionId, $institutionIds);
        }

        // Se não houver método específico, verificar se o usuário tem a instituição diretamente associada
        if (method_exists($user, 'institutions')) {
            $institutions = $user->institutions()->get();
            $institutionIds = $institutions->pluck('id')->toArray();
            return in_array($institutionId, $institutionIds);
        }

        // Para fins de desenvolvimento/teste, permitir tudo
        return true;
    }

    /**
     * Lista todos os pontos próximos a uma localização
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nearbyPoints(Request $request)
    {
        try {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'sometimes|numeric|min:0.1|max:50', // raio em km
                'limit' => 'sometimes|integer|min:1|max:100',
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            $latitude = $validated['latitude'];
            $longitude = $validated['longitude'];
            $radius = $validated['radius'] ?? 2; // 2km de raio por padrão
            $limit = $validated['limit'] ?? 20; // 20 pontos por padrão

            // Se uma instituição foi especificada, considerar também seus ancestrais
            $institutionIds = null;
            if (isset($validated['institution_id'])) {
                $institution = Institution::findOrFail($validated['institution_id']);
                $ancestorIds = $this->getAncestorInstitutionIds($institution);
                $institutionIds = array_merge([$validated['institution_id']], $ancestorIds);
            }

            // Usar o método estático do modelo Point para encontrar pontos próximos
            $points = Point::findNearby($latitude, $longitude, $radius, $limit, $institutionIds);

            // Formatar os resultados para incluir a distância em km
            $points = $points->map(function ($point) {
                $point->distance_km = round($point->distance / 1000, 2);
                return $point;
            });

            return response()->json([
                'success' => true,
                'points' => $points,
                'count' => $points->count(),
                'search' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radius
                ]
            ]);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar pontos próximos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém estatísticas de uso para os pontos de uma instituição
     *
     * @param int $institutionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPointsUsageStats($institutionId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);
// Obter todas as instituições descendentes (filhas, netas, etc.)
            $descendantIds = $this->getDescendantInstitutionIds($institution);

            // Lista de IDs de instituições a serem consideradas (a própria instituição + descendentes)
            $institutionIds = array_merge([$institutionId], $descendantIds);

            // Buscar pontos da própria instituição
            $ownPoints = Point::where('institution_id', $institutionId)
                ->where('is_active', true)
                ->get();

            // Estatísticas de uso dos pontos próprios
            $ownPointsStats = $ownPoints->map(function ($point) use ($institutionIds) {
                // Total de rotas que usam este ponto em qualquer instituição
                $totalRouteCount = $this->getRouteCountForPoint($point->id);

                // Rotas das instituições descendentes que usam este ponto
                $institutionRouteCount = $this->getRouteCountForPointAndInstitutions($point->id, $institutionIds);

                return [
                    'id' => $point->id,
                    'name' => $point->name,
                    'type' => $point->type,
                    'total_routes' => $totalRouteCount,
                    'institution_routes' => $institutionRouteCount,
                    'external_routes' => $totalRouteCount - $institutionRouteCount,
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ];
            });

            // Estatísticas gerais
            $totalPoints = $ownPoints->count();
            $totalUsedPoints = $ownPointsStats->filter(function ($stat) {
                return $stat['total_routes'] > 0;
            })->count();

            $mostUsedPoints = $ownPointsStats->sortByDesc('total_routes')->take(5)->values();

            return response()->json([
                'success' => true,
                'points_stats' => [
                    'total_points' => $totalPoints,
                    'used_points' => $totalUsedPoints,
                    'unused_points' => $totalPoints - $totalUsedPoints,
                    'usage_percentage' => $totalPoints > 0 ? round(($totalUsedPoints / $totalPoints) * 100, 2) : 0,
                    'most_used_points' => $mostUsedPoints,
                    'all_points' => $ownPointsStats
                ],
                'institution' => [
                    'id' => $institution->id,
                    'name' => $institution->name,
                    'descendant_count' => count($descendantIds)
                ]
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição não encontrada'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas de uso dos pontos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém todos os IDs de instituições descendentes (filhas, netas, etc.)
     *
     * @param Institution $institution
     * @return array
     */
    private function getDescendantInstitutionIds(Institution $institution)
    {
        // Array para armazenar os IDs de todos os descendentes
        $descendantIds = [];

        // Fila para percorrer a árvore de instituições (BFS)
        $queue = [$institution->id];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            // Buscar todas as instituições filhas diretas
            $children = Institution::where('parent_id', $currentId)->get();

            foreach ($children as $child) {
                $descendantIds[] = $child->id;
                $queue[] = $child->id; // Adicionar à fila para processar seus filhos
            }
        }

        return $descendantIds;
    }

    /**
     * Obtém o número de rotas de instituições específicas que passam por um ponto
     *
     * @param int $pointId
     * @param array $institutionIds
     * @return int
     */
    private function getRouteCountForPointAndInstitutions($pointId, $institutionIds)
    {
        // No sistema real, isso seria uma contagem de rotas que usam o ponto,
        // filtrando por instituições específicas
        // Por agora, retornamos um valor aleatório para demonstração
        // Em um sistema real, você implementaria algo como:
        /*
        if (Schema::hasTable('route_points') && Schema::hasTable('routes')) {
            return DB::table('route_points')
                ->join('routes', 'route_points.route_id', '=', 'routes.id')
                ->where('route_points.point_id', $pointId)
                ->whereIn('routes.institution_id', $institutionIds)
                ->distinct('routes.id')
                ->count('routes.id');
        }
        */

        // Valor de demonstração - menos que o total de rotas
        $totalRoutes = $this->getRouteCountForPoint($pointId);
        return min($totalRoutes, rand(0, $totalRoutes));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Routes\RoutePoint;
use App\Models\Users\Routes\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoutePointController extends Controller
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
            $points = RoutePoint::whereIn('institution_id', $institutionIds)
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

            Log::error('Erro ao buscar pontos da instituição: ' . $e->getMessage());
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
        // Verificar se existe a tabela route_points
        if (Schema::hasTable('route_points')) {
            return DB::table('route_points')
                ->where('point_id', $pointId)
                ->distinct('route_id')
                ->count('route_id');
        }

        // Se não existir a tabela route_points, retornar 0
        return 0;
    }


    /**
     * Obtém todos os pontos de uma rota
     *
     * @param int $routeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoutePoints($routeId)
    {
        try {
            $route = Route::query()->findOrFail($routeId);

            // Verificar se a rota pertence ao usuário autenticado
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta rota'
                ], 403);
            }

            $points = $route->points()->orderBy('sequence')->get();

            return response()->json([
                'success' => true,
                'points' => $points
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rota não encontrada'
                ], 404);
            }

            Log::error('Erro ao buscar pontos da rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar pontos da rota',
                'error' => $e->getMessage()
            ], 500);
        }
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
            $point = RoutePoint::query()->findOrFail($id);

            // Verificar se o ponto pertence a uma rota do usuário autenticado
            $route = $point->route;
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar este ponto'
                ], 403);
            }

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

            Log::error('Erro ao buscar ponto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Armazena um novo ponto na rota
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'route_id' => 'required|exists:routes,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'sequence' => 'nullable|integer|min:0',
            ]);

            // Verificar se a rota pertence ao usuário autenticado
            $route = Route::query()->findOrFail($validated['route_id']);
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta rota'
                ], 403);
            }

            // Se a sequência não foi fornecida, adicionar como último ponto
            if (!isset($validated['sequence'])) {
                $lastPoint = $route->points()->orderBy('sequence', 'desc')->first();
                $validated['sequence'] = $lastPoint ? $lastPoint->sequence + 1 : 0;
            } else {
                // Se a sequência foi fornecida, reordenar os pontos existentes
                $this->reorderPoints($route, $validated['sequence']);
            }

            $point = new RoutePoint();
            $point->fill($validated);
            $point->save();

            // Marcar a rota como não calculada após adicionar um ponto
            $route->last_calculated_at = null;
            $route->save();

            return response()->json([
                'success' => true,
                'point' => $point,
                'message' => 'Ponto adicionado com sucesso'
            ], 201);
        } catch (Exception $e) {
            Log::error('Erro ao criar ponto: ' . $e->getMessage());
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
        DB::beginTransaction();

        try {
            $point = RoutePoint::query()->findOrFail($id);

            // Verificar se o ponto pertence a uma rota do usuário autenticado
            $route = $point->route;
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar este ponto'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'latitude' => 'sometimes|required|numeric|between:-90,90',
                'longitude' => 'sometimes|required|numeric|between:-180,180',
                'sequence' => 'sometimes|required|integer|min:0',
            ]);

            // Se a sequência foi alterada, reordenar os pontos
            if (isset($validated['sequence']) && $validated['sequence'] != $point->sequence) {
                $this->reorderPoints($route, $validated['sequence'], $point->id);
            }

            $point->fill($validated);
            $point->save();

            // Marcar a rota como não calculada após modificar um ponto
            if ($point->isDirty(['latitude', 'longitude'])) {
                $route->last_calculated_at = null;
                $route->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'point' => $point,
                'message' => 'Ponto atualizado com sucesso'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ponto não encontrado'
                ], 404);
            }

            Log::error('Erro ao atualizar ponto: ' . $e->getMessage());
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
        DB::beginTransaction();

        try {
            $point = RoutePoint::query()->findOrFail($id);

            // Verificar se o ponto pertence a uma rota do usuário autenticado
            $route = $point->route;
            if ($route->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar este ponto'
                ], 403);
            }

            // Armazenar a sequência do ponto a ser removido
            $sequence = $point->sequence;

            // Remover o ponto
            $point->delete();

            // Reordenar os pontos após a remoção
            $route->points()
                ->where('sequence', '>', $sequence)
                ->update(['sequence' => DB::raw('sequence - 1')]);

            // Marcar a rota como não calculada após remover um ponto
            $route->last_calculated_at = null;
            $route->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ponto removido com sucesso'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ponto não encontrado'
                ], 404);
            }

            Log::error('Erro ao remover ponto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover ponto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordena os pontos da rota quando um novo ponto é inserido ou um ponto existente muda de posição
     *
     * @param Route $route
     * @param int $newSequence
     * @param int|null $excludePointId ID do ponto a excluir da reordenação (em caso de atualização)
     * @return void
     */
    private function reorderPoints(Route $route, int $newSequence, ?int $excludePointId = null)
    {
        // Em caso de atualização, pegar a sequência atual do ponto
        $currentSequence = null;
        if ($excludePointId) {
            $point = RoutePoint::query()->findOrFail($excludePointId);
            $currentSequence = $point->sequence;
        }

        if ($currentSequence !== null) {
            // Caso de atualização de sequência
            if ($newSequence > $currentSequence) {
                // Movendo para frente (decrementar pontos entre a posição atual e a nova)
                $route->points()
                    ->where('id', '!=', $excludePointId)
                    ->where('sequence', '>', $currentSequence)
                    ->where('sequence', '<=', $newSequence)
                    ->update(['sequence' => DB::raw('sequence - 1')]);
            } else if ($newSequence < $currentSequence) {
                // Movendo para trás (incrementar pontos entre a nova posição e a atual)
                $route->points()
                    ->where('id', '!=', $excludePointId)
                    ->where('sequence', '>=', $newSequence)
                    ->where('sequence', '<', $currentSequence)
                    ->update(['sequence' => DB::raw('sequence + 1')]);
            }
        } else {
            // Caso de inserção de novo ponto
            $route->points()
                ->where('sequence', '>=', $newSequence)
                ->update(['sequence' => DB::raw('sequence + 1')]);
        }
    }
}

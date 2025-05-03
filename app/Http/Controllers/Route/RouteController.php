<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RoutePoint;
use App\Services\RouteService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RouteController extends Controller
{
    protected $routeService;

    public function __construct(RouteService $routeService)
    {
        $this->routeService = $routeService;
    }

    /**
     * Lista todas as rotas do usuário autenticado
     */
    public function index(): JsonResponse
    {
        $routes = Route::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'total_distance', 'total_duration', 'is_published', 'last_calculated_at', 'created_at']);

        return response()->json(['routes' => $routes]);
    }

    /**
     * Cria uma nova rota
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'points' => 'required|array|min:2',
            'points.*.latitude' => 'required|numeric|between:-90,90',
            'points.*.longitude' => 'required|numeric|between:-180,180',
            'points.*.name' => 'nullable|string|max:255',
            'points.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Criar a rota
            $route = Route::create([
                'name' => $request->input('name'),
                'user_id' => Auth::id(),
            ]);

            // Adicionar pontos
            foreach ($request->input('points') as $index => $pointData) {
                $route->points()->create([
                    'sequence' => $index,
                    'name' => $pointData['name'] ?? "Ponto " . ($index + 1),
                    'description' => $pointData['description'] ?? null,
                    'latitude' => $pointData['latitude'],
                    'longitude' => $pointData['longitude'],
                ]);
            }

            // Calcular a rota
            $this->routeService->calculateRoute($route);

            DB::commit();

            return response()->json([
                'message' => 'Rota criada com sucesso',
                'route' => $this->routeService->getRouteForFrontend($route),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar rota: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao criar rota',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtém os detalhes de uma rota específica
     */
    public function show(Route $route): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id() && !$route->is_published) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        return response()->json([
            'route' => $this->routeService->getRouteForFrontend($route),
        ]);
    }

    /**
     * Atualiza uma rota existente
     */
    public function update(Request $request, Route $route): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_published' => 'sometimes|boolean',
            'points' => 'sometimes|array|min:2',
            'points.*.id' => 'sometimes|nullable|exists:route_points,id',
            'points.*.latitude' => 'required|numeric|between:-90,90',
            'points.*.longitude' => 'required|numeric|between:-180,180',
            'points.*.name' => 'nullable|string|max:255',
            'points.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Atualizar dados básicos da rota
            if ($request->has('name')) {
                $route->name = $request->input('name');
            }

            if ($request->has('is_published')) {
                $route->is_published = $request->input('is_published');
            }

            $route->save();

            // Se os pontos foram fornecidos, atualizá-los
            if ($request->has('points')) {
                // Remover pontos existentes
                $route->points()->delete();
                $route->segments()->delete();

                // Adicionar novos pontos
                foreach ($request->input('points') as $index => $pointData) {
                    $route->points()->create([
                        'sequence' => $index,
                        'name' => $pointData['name'] ?? "Ponto " . ($index + 1),
                        'description' => $pointData['description'] ?? null,
                        'latitude' => $pointData['latitude'],
                        'longitude' => $pointData['longitude'],
                    ]);
                }

                // Recalcular a rota
                $this->routeService->calculateRoute($route, true);
            }

            DB::commit();

            return response()->json([
                'message' => 'Rota atualizada com sucesso',
                'route' => $this->routeService->getRouteForFrontend($route),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar rota: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao atualizar rota',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove uma rota
     */
    public function destroy(Route $route): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        try {
            $route->delete();

            return response()->json([
                'message' => 'Rota excluída com sucesso',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao excluir rota: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao excluir rota',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Adiciona um novo ponto à rota
     */
    public function addPoint(Request $request, Route $route): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'after_point_id' => 'nullable|exists:route_points,id',
        ]);

        DB::beginTransaction();

        try {
            $points = $route->points()->orderBy('sequence')->get();

            // Determinar a posição do novo ponto
            $newSequence = 0;
            $afterPointId = $request->input('after_point_id');

            if ($afterPointId) {
                $afterPoint = $points->firstWhere('id', $afterPointId);
                if ($afterPoint) {
                    $newSequence = $afterPoint->sequence + 1;

                    // Incrementar sequência dos pontos seguintes
                    $route->points()
                        ->where('sequence', '>=', $newSequence)
                        ->increment('sequence');
                }
            } else {
                // Adicionar no final da rota
                $newSequence = $points->count();
            }

            // Criar o novo ponto
            $newPoint = $route->points()->create([
                'sequence' => $newSequence,
                'name' => $request->input('name') ?? "Ponto " . ($newSequence + 1),
                'description' => $request->input('description'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ]);

            // Recalcular a rota
            $this->routeService->calculateRoute($route);

            DB::commit();

            return response()->json([
                'message' => 'Ponto adicionado com sucesso',
                'point' => $newPoint,
                'route' => $this->routeService->getRouteForFrontend($route),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erro ao adicionar ponto: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao adicionar ponto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove um ponto da rota
     */
    public function removePoint(Route $route, RoutePoint $point): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        // Verificar se o ponto pertence à rota
        if ($point->route_id !== $route->id) {
            return response()->json(['message' => 'O ponto não pertence a esta rota'], 400);
        }

        DB::beginTransaction();

        try {
            $sequence = $point->sequence;

            // Se a rota ficar com menos de 2 pontos, não permitir a remoção
            if ($route->points()->count() <= 2) {
                return response()->json([
                    'message' => 'A rota deve ter pelo menos 2 pontos',
                ], 400);
            }

            // Remover o ponto
            $point->delete();

            // Recalcular a rota considerando a remoção do ponto
            $this->routeService->recalculateAfterPointRemoval($route, $sequence);

            DB::commit();

            return response()->json([
                'message' => 'Ponto removido com sucesso',
                'route' => $this->routeService->getRouteForFrontend($route),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erro ao remover ponto: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao remover ponto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza a posição de um ponto na rota
     */
    public function updatePointPosition(Request $request, Route $route, RoutePoint $point): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        // Verificar se o ponto pertence à rota
        if ($point->route_id !== $route->id) {
            return response()->json(['message' => 'O ponto não pertence a esta rota'], 400);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::beginTransaction();

        try {
            // Atualizar a posição do ponto
            $point->update([
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ]);

            // Encontrar segmentos que usam este ponto
            $segmentsToRecalculate = $route->segments()
                ->where('start_point_id', $point->id)
                ->orWhere('end_point_id', $point->id)
                ->get();

            // Remover os segmentos afetados
            foreach ($segmentsToRecalculate as $segment) {
                $segment->delete();
            }

            // Recalcular a rota
            $this->routeService->calculateRoute($route);

            DB::commit();

            return response()->json([
                'message' => 'Posição do ponto atualizada com sucesso',
                'route' => $this->routeService->getRouteForFrontend($route),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar posição do ponto: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao atualizar posição do ponto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Força o recálculo completo de uma rota
     */
    public function recalculateRoute(Route $route): JsonResponse
    {
        // Verificar se o usuário tem acesso à rota
        if ($route->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        try {
            // Forçar recálculo completo
            $this->routeService->calculateRoute($route, true);

            return response()->json([
                'message' => 'Rota recalculada com sucesso',
                'route' => $this->routeService->getRouteForFrontend($route),
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao recalcular rota: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao recalcular rota',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

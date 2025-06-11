<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class PermissionStatsController extends Controller
{
    /**
     * Estatísticas de permissões de uma instituição
     */
    public function getInstitutionStats($institutionId)
    {
        try {
            $user = Auth::user();

            if (!$user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            // Verificar se o usuário pode acessar a instituição
            if (!$this->userCanAccessInstitution($user, $institutionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar estatísticas desta instituição'
                ], 403);
            }

            $stats = Permission::forInstitution($institutionId)
                ->selectRaw('
                    COUNT(*) as total_permissions,
                    COUNT(CASE WHEN is_active = true THEN 1 END) as active_permissions,
                    COUNT(CASE WHEN is_active = false THEN 1 END) as inactive_permissions
                ')
                ->first();

            // Estatísticas adicionais
            $additionalStats = [
                'permissions_with_users' => Permission::forInstitution($institutionId)
                    ->whereHas('users')
                    ->count(),
                'permissions_with_routes' => Permission::forInstitution($institutionId)
                    ->whereHas('routes')
                    ->count(),
                'total_user_permissions' => Permission::forInstitution($institutionId)
                    ->withCount('users')
                    ->get()
                    ->sum('users_count'),
            ];

            return response()->json([
                'success' => true,
                'institution_id' => (int) $institutionId,
                'stats' => array_merge($stats->toArray(), $additionalStats)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar estatísticas de permissões',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permissões mais utilizadas
     */
    public function getPopularPermissions(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'limit' => 'sometimes|integer|min:1|max:50',
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            $query = Permission::withCount(['users', 'routes']);

            // Filtrar por instituição se especificada
            if (!empty($validated['institution_id'])) {
                if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para acessar esta instituição'
                    ], 403);
                }
                $query->forInstitution($validated['institution_id']);
            } else {
                // Se não especificou instituição, mostrar apenas das instituições do usuário
                if (!$user->isAdmin()) {
                    $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();
                    $query->whereIn('institution_id', $userInstitutionIds);
                }
            }

            $limit = $validated['limit'] ?? 10;
            $permissions = $query->orderBy('users_count', 'desc')
                ->limit($limit)
                ->with('institution')
                ->get();

            return response()->json([
                'success' => true,
                'popular_permissions' => $permissions,
                'limit' => $limit
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar permissões populares',
                'error' => $e->getMessage()
            ], 500);
        }
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
}

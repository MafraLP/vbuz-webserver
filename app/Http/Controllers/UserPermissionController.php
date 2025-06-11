<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class UserPermissionController extends Controller
{
    /**
     * Verifica se usuário tem permissão específica
     */
    public function hasPermission($userId, $permissionId)
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $user = User::findOrFail($userId);
            $permission = Permission::findOrFail($permissionId);

            // Verificar se o usuário atual pode verificar permissões do usuário alvo
            if (!$this->userCanAccessTargetUser($currentUser, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para verificar permissões deste usuário'
                ], 403);
            }

            $hasPermission = $permission->userHasPermission($user);

            return response()->json([
                'success' => true,
                'user_id' => (int) $userId,
                'permission_id' => (int) $permissionId,
                'has_permission' => $hasPermission,
                'user_name' => $user->name,
                'permission_name' => $permission->name
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'verificar permissão do usuário');
        }
    }

    /**
     * Lista todas as permissões ativas de um usuário
     */
    public function getUserPermissions($userId, Request $request)
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'include_expired' => 'sometimes|boolean',
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            $user = User::with(['permissions' => function($query) use ($validated) {
                if (empty($validated['include_expired'])) {
                    $query->wherePivot('is_active', true)
                        ->wherePivot(function($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                }

                if (!empty($validated['institution_id'])) {
                    $query->where('institution_id', $validated['institution_id']);
                }

                $query->with('institution')
                    ->orderBy('name');
            }])->findOrFail($userId);

            // Verificar se o usuário atual pode ver permissões do usuário alvo
            if (!$this->userCanAccessTargetUser($currentUser, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar permissões deste usuário'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'permissions' => $user->permissions,
                'total_permissions' => $user->permissions->count()
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar permissões do usuário');
        }
    }

    /**
     * Busca usuários por múltiplas permissões
     */
    public function searchUsersByPermissions(Request $request)
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'permission_codes' => 'required|array|min:1',
                'permission_codes.*' => 'string|exists:permissions,code',
                'match_all' => 'sometimes|boolean',
                'institution_id' => 'sometimes|exists:institutions,id',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            // Verificar se o usuário pode acessar a instituição (se especificada)
            if (!empty($validated['institution_id'])) {
                if (!$this->userCanAccessInstitution($currentUser, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para buscar usuários desta instituição'
                    ], 403);
                }
            }

            $permissionQuery = Permission::whereIn('code', $validated['permission_codes']);

            if (!empty($validated['institution_id'])) {
                $permissionQuery->forInstitution($validated['institution_id']);
            }

            $permissionIds = $permissionQuery->pluck('id')->toArray();

            if (empty($permissionIds)) {
                return response()->json([
                    'success' => true,
                    'searched_permissions' => $validated['permission_codes'],
                    'users' => [],
                    'message' => 'Nenhuma permissão encontrada com os códigos fornecidos'
                ]);
            }

            $query = User::whereHas('permissions', function($q) use ($permissionIds, $validated) {
                $q->whereIn('permission_id', $permissionIds)
                    ->wherePivot('is_active', true)
                    ->wherePivot(function($pivot) {
                        $pivot->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });

                if (!empty($validated['match_all'])) {
                    // Usuário deve ter TODAS as permissões
                    $q->havingRaw('COUNT(DISTINCT permission_id) = ?', [count($permissionIds)]);
                }
            });

            // Filtrar por instituições se o usuário não for admin
            if (!$currentUser->isAdmin()) {
                $userInstitutionIds = $currentUser->getInstitutions()->pluck('id')->toArray();
                $query->whereHas('institutions', function($q) use ($userInstitutionIds) {
                    $q->whereIn('institutions.id', $userInstitutionIds);
                });
            }

            $perPage = $validated['per_page'] ?? 20;
            $users = $query->with(['permissions' => function($q) use ($permissionIds) {
                $q->whereIn('permission_id', $permissionIds)
                    ->with('institution');
            }])->paginate($perPage);

            return response()->json([
                'success' => true,
                'searched_permissions' => $validated['permission_codes'],
                'match_all' => $validated['match_all'] ?? false,
                'users' => $users
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar usuários por permissões');
        }
    }

    /**
     * Verifica se o usuário atual pode acessar dados do usuário alvo
     */
    private function userCanAccessTargetUser(User $currentUser, User $targetUser): bool
    {
        // Admin pode acessar qualquer usuário
        if ($currentUser->isAdmin()) {
            return true;
        }

        // Usuário pode acessar seus próprios dados
        if ($currentUser->id === $targetUser->id) {
            return true;
        }

        // Verificar se ambos pertencem a pelo menos uma instituição em comum
        $currentUserInstitutions = $currentUser->getInstitutions()->pluck('id')->toArray();
        $targetUserInstitutions = $targetUser->getInstitutions()->pluck('id')->toArray();

        return !empty(array_intersect($currentUserInstitutions, $targetUserInstitutions));
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
     * Trata exceções de forma padronizada
     */
    private function handleException(Exception $e, string $action): \Illuminate\Http\JsonResponse
    {
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário ou permissão não encontrados'
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => "Erro ao {$action}",
            'error' => $e->getMessage()
        ], 500);
    }
}

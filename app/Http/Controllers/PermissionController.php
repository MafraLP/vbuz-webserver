<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class PermissionController extends Controller
{
    /**
     * Lista todas as permissões/carteirinhas
     */
    public function index(Request $request)
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
                'institution_id' => 'sometimes|exists:institutions,id',
                'active_only' => 'sometimes|boolean',
                'search' => 'sometimes|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = Permission::query();

            // Filtrar por instituição se especificada
            if (!empty($validated['institution_id'])) {
                if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para acessar permissões desta instituição'
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

            // Filtrar apenas ativas se solicitado
            if (!empty($validated['active_only'])) {
                $query->active();
            }

            // Busca por texto
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('code', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            $query->with(['institution'])
                ->orderBy('name');

            $perPage = $validated['per_page'] ?? 20;
            $permissions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'permissions' => $permissions
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar permissões',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma permissão específica
     */
    public function show($id)
    {
        try {
            $permission = Permission::with(['institution', 'routes', 'users'])->findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanAccessPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta carteirinha'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'permission' => $permission,
                'stats' => $permission->getStats()
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar permissão');
        }
    }

    /**
     * Cria uma nova permissão/carteirinha
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:permissions',
                'description' => 'nullable|string',
                'color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
                'icon' => 'nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
                'institution_id' => 'required|exists:institutions,id',
            ]);

            $user = Auth::user();

            if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar permissões nesta instituição'
                ], 403);
            }

            $permission = Permission::create($validated);

            return response()->json([
                'success' => true,
                'permission' => $permission->load('institution'),
                'message' => 'Carteirinha criada com sucesso'
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar carteirinha',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma permissão/carteirinha
     */
    public function update(Request $request, $id)
    {
        try {
            $permission = Permission::findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:permissions,code,' . $id,
                'description' => 'sometimes|nullable|string',
                'color' => 'sometimes|nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
                'icon' => 'sometimes|nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            if (isset($validated['institution_id']) && $validated['institution_id'] !== $permission->institution_id) {
                if (!$this->userCanAccessInstitution($user, $validated['institution_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para mover a carteirinha para esta instituição'
                    ], 403);
                }
            }

            $permission->update($validated);

            return response()->json([
                'success' => true,
                'permission' => $permission->load('institution'),
                'message' => 'Carteirinha atualizada com sucesso'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'atualizar carteirinha');
        }
    }

    /**
     * Remove uma permissão/carteirinha
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $permission = Permission::findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir esta carteirinha'
                ], 403);
            }

            // Remover relacionamentos
            $permission->routes()->detach();
            $permission->users()->detach();
            $permission->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Carteirinha removida com sucesso'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'remover carteirinha');
        }
    }

    /**
     * Concede uma permissão a um usuário
     */
    public function grantToUser(Request $request, $id)
    {
        try {
            $permission = Permission::findOrFail($id);
            $currentUser = Auth::user();

            if (!$this->userCanModifyPermission($currentUser, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para gerenciar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'expires_at' => 'nullable|date|after:now',
            ]);

            $targetUser = User::findOrFail($validated['user_id']);

            $permission->grantToUser(
                $targetUser,
                $currentUser,
                isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Carteirinha concedida com sucesso'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'conceder carteirinha');
        }
    }

    /**
     * Revoga uma permissão de um usuário
     */
    public function revokeFromUser(Request $request, $id)
    {
        try {
            $permission = Permission::findOrFail($id);
            $currentUser = Auth::user();

            if (!$this->userCanModifyPermission($currentUser, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para gerenciar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $targetUser = User::findOrFail($validated['user_id']);
            $permission->revokeFromUser($targetUser);

            return response()->json([
                'success' => true,
                'message' => 'Carteirinha revogada com sucesso'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'revogar carteirinha');
        }
    }

    /**
     * Lista usuários com uma permissão específica
     */
    public function getUsers($id, Request $request)
    {
        try {
            $permission = Permission::findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanAccessPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'active_only' => 'sometimes|boolean',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = $permission->users();

            if (!empty($validated['active_only'])) {
                $query = $permission->activeUsers();
            }

            $perPage = $validated['per_page'] ?? 20;
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'permission' => $permission,
                'users' => $users
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar usuários da carteirinha');
        }
    }

    /**
     * Lista rotas que requerem uma permissão específica
     */
    public function getRoutes($id, Request $request)
    {
        try {
            $permission = Permission::findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanAccessPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'published_only' => 'sometimes|boolean',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = $permission->routes();

            if (!empty($validated['published_only'])) {
                $query->where('is_published', true);
            }

            $query->with(['institution', 'points' => function($q) {
                $q->orderBy('sequence');
            }]);

            $perPage = $validated['per_page'] ?? 20;
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'permission' => $permission,
                'routes' => $routes
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar rotas da carteirinha');
        }
    }

    /**
     * Ativa ou desativa uma permissão
     */
    public function toggleStatus($id, Request $request)
    {
        try {
            $permission = Permission::findOrFail($id);
            $user = Auth::user();

            if (!$this->userCanModifyPermission($user, $permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para modificar esta carteirinha'
                ], 403);
            }

            $validated = $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $permission->is_active = $validated['is_active'];
            $permission->save();

            $message = $permission->is_active ? 'Carteirinha ativada com sucesso' : 'Carteirinha desativada com sucesso';

            return response()->json([
                'success' => true,
                'permission' => $permission,
                'message' => $message
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'alterar status da carteirinha');
        }
    }

    /**
     * Busca permissões disponíveis para um usuário específico
     */
    public function availableForUser($userId, Request $request)
    {
        try {
            $currentUser = Auth::user();
            $targetUser = User::findOrFail($userId);

            // Verificar se o usuário atual pode gerenciar permissões para o usuário alvo
            if (!$currentUser->isAdmin()) {
                $currentUserInstitutions = $currentUser->getInstitutions()->pluck('id')->toArray();
                $targetUserInstitutions = $targetUser->getInstitutions()->pluck('id')->toArray();

                if (empty(array_intersect($currentUserInstitutions, $targetUserInstitutions))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Você não tem permissão para gerenciar permissões deste usuário'
                    ], 403);
                }
            }

            $validated = $request->validate([
                'institution_id' => 'sometimes|exists:institutions,id',
            ]);

            $query = Permission::active();

            // Filtrar por instituição se especificada
            if (!empty($validated['institution_id'])) {
                $query->forInstitution($validated['institution_id']);
            } else {
                // Buscar permissões das instituições comuns
                if (!$currentUser->isAdmin()) {
                    $institutionIds = array_intersect(
                        $currentUser->getInstitutions()->pluck('id')->toArray(),
                        $targetUser->getInstitutions()->pluck('id')->toArray()
                    );
                    $query->whereIn('institution_id', $institutionIds);
                }
            }

            $permissions = $query->with('institution')->get();

            // Marcar quais permissões o usuário já possui
            $userPermissions = $targetUser->permissions()->pluck('permissions.id')->toArray();

            $permissions = $permissions->map(function($permission) use ($userPermissions) {
                $permission->user_has_permission = in_array($permission->id, $userPermissions);
                return $permission;
            });

            return response()->json([
                'success' => true,
                'user' => $targetUser,
                'permissions' => $permissions
            ]);

        } catch (Exception $e) {
            return $this->handleException($e, 'buscar permissões disponíveis');
        }
    }

    // === MÉTODOS PRIVADOS DE AUXÍLIO ===

    /**
     * Verifica se o usuário pode acessar a permissão
     */
    private function userCanAccessPermission(User $user, Permission $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $userInstitutionIds = $user->getInstitutions()->pluck('id')->toArray();
        return in_array($permission->institution_id, $userInstitutionIds);
    }

    /**
     * Verifica se o usuário pode modificar a permissão
     */
    private function userCanModifyPermission(User $user, Permission $permission): bool
    {
        return $this->userCanAccessPermission($user, $permission);
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
                'message' => 'Carteirinha não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => "Erro ao {$action}",
            'error' => $e->getMessage()
        ], 500);
    }
}

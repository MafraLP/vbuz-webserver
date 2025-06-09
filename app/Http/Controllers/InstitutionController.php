<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class InstitutionController extends Controller
{
    /**
     * Obtém todas as instituições associadas ao usuário atual
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInstitutions()
    {
        try {
            $user = Auth::user();
            // Usar o método getInstitutions() em vez de acessar a propriedade institutions
            $institutions = $user->getInstitutions();

            return response()->json([
                'success' => true,
                'institutions' => $institutions
            ]);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar instituições do usuário',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma instituição específica
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $institution = Institution::query()->findOrFail($id);

            return response()->json([
                'success' => true,
                'institution' => $institution
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
                'message' => 'Erro ao buscar instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Armazena uma nova instituição
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|max:50',
                'description' => 'nullable|string',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'parent_id' => 'nullable|exists:institutions,id',
                'prefecture_id' => 'nullable|exists:institutions,id',
            ]);

            $institution = new Institution();
            $institution->fill($validated);
            $institution->save();

            return response()->json([
                'success' => true,
                'institution' => $institution,
                'message' => 'Instituição criada com sucesso'
            ], 201);
        } catch (Exception $e) {
                        return response()->json([
                'success' => false,
                'message' => 'Erro ao criar instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma instituição existente
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $institution = Institution::query()->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|string|max:50',
                'description' => 'nullable|string',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'parent_id' => 'nullable|exists:institutions,id',
                'prefecture_id' => 'nullable|exists:institutions,id',
            ]);

            $institution->fill($validated);
            $institution->save();

            return response()->json([
                'success' => true,
                'institution' => $institution,
                'message' => 'Instituição atualizada com sucesso'
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
                'message' => 'Erro ao atualizar instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove uma instituição
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $institution = Institution::query()->findOrFail($id);
            $institution->delete();

            return response()->json([
                'success' => true,
                'message' => 'Instituição removida com sucesso'
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
                'message' => 'Erro ao remover instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtém todos os usuários de uma instituição
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstitutionUsers($id)
    {
        try {
            $institution = Institution::query()->findOrFail($id);
            $users = $institution->users()->get();

            return response()->json([
                'success' => true,
                'users' => $users
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
                'message' => 'Erro ao buscar usuários da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona um usuário a uma instituição
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUserToInstitution(Request $request, $id)
    {
        try {
            $institution = Institution::query()->findOrFail($id);

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'nullable|string|max:50',
            ]);

            $user = User::query()->findOrFail($validated['user_id']);

            // Assumindo que existe uma tabela pivot com campos extras como 'role'
            if (isset($validated['role'])) {
                $institution->users()->attach($user->id, ['role' => $validated['role']]);
            } else {
                $institution->users()->attach($user->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuário adicionado à instituição com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição ou usuário não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar usuário à instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um usuário de uma instituição
     *
     * @param int $institutionId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeUserFromInstitution($institutionId, $userId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);
            $user = User::query()->findOrFail($userId);

            $institution->users()->detach($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Usuário removido da instituição com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição ou usuário não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao remover usuário da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

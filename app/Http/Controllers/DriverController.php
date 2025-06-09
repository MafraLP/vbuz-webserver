<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Users\User;
use App\Models\Users\Profiles\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class DriverController extends Controller
{
    /**
     * Obtém todos os motoristas de uma instituição
     *
     * @param int $institutionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstitutionDrivers($institutionId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);

            // Buscar os perfis de motorista associados a esta instituição
            $driverProfiles = $institution->driverProfiles()->with('user')->get();

            // Formatar os dados para retornar informações completas de cada motorista
            $drivers = $driverProfiles->map(function ($profile) {
                return [
                    'profile_id' => $profile->id,
                    'user_id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'email' => $profile->user->email,
                    'phone' => $profile->user->phone,
                    'license_number' => $profile->license_number,
                    'license_type' => $profile->license_type,
                    'license_expiry' => $profile->license_expiry,
                    'status' => $profile->status,
                    'hire_date' => $profile->hire_date,
                    'employment_type' => $profile->employment_type,
                    'notes' => $profile->notes,
                    // Adicionar dados do pivot se necessário
                    'pivot' => $profile->pivot ? [
                        'start_date' => $profile->pivot->start_date,
                        'end_date' => $profile->pivot->end_date,
                        'status' => $profile->pivot->status,
                        'contract_type' => $profile->pivot->contract_type,
                        'schedule' => $profile->pivot->schedule,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'drivers' => $drivers
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
                'message' => 'Erro ao buscar motoristas da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um motorista específico
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $driverProfile = DriverProfile::query()->with('user')->findOrFail($id);

            $driver = [
                'profile_id' => $driverProfile->id,
                'user_id' => $driverProfile->user->id,
                'name' => $driverProfile->user->name,
                'email' => $driverProfile->user->email,
                'phone' => $driverProfile->user->phone,
                'license_number' => $driverProfile->license_number,
                'license_type' => $driverProfile->license_type,
                'license_expiry' => $driverProfile->license_expiry,
                'status' => $driverProfile->status,
                'hire_date' => $driverProfile->hire_date,
                'employment_type' => $driverProfile->employment_type,
                'notes' => $driverProfile->notes,
                // Carregar também as instituições do motorista
                'institutions' => $driverProfile->institutions()->get()
            ];

            return response()->json([
                'success' => true,
                'driver' => $driver
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Motorista não encontrado'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar motorista',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona um motorista a uma instituição
     *
     * @param \Illuminate\Http\Request $request
     * @param int $institutionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addDriverToInstitution(Request $request, $institutionId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);

            $validated = $request->validate([
                'driver_profile_id' => 'required|exists:driver_profiles,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'nullable|string|in:active,inactive,temporary',
                'contract_type' => 'nullable|string',
                'schedule' => 'nullable|json',
                'notes' => 'nullable|string',
            ]);

            // Verificar se o motorista já está associado a esta instituição
            $alreadyAssociated = $institution->driverProfiles()
                ->where('driver_profile_id', $validated['driver_profile_id'])
                ->exists();

            if ($alreadyAssociated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este motorista já está associado a esta instituição'
                ], 422);
            }

            // Adicionar o motorista à instituição
            $institution->driverProfiles()->attach($validated['driver_profile_id'], [
                'start_date' => $validated['start_date'] ?? now(),
                'end_date' => $validated['end_date'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'contract_type' => $validated['contract_type'] ?? 'permanent',
                'schedule' => $validated['schedule'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Motorista adicionado à instituição com sucesso'
            ], 201);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição não encontrada'
                ], 404);
            }

                        return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar motorista à instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um motorista de uma instituição
     *
     * @param int $institutionId
     * @param int $driverProfileId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeDriverFromInstitution($institutionId, $driverProfileId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);

            // Verificar se o motorista está associado a esta instituição
            $isAssociated = $institution->driverProfiles()
                ->where('driver_profile_id', $driverProfileId)
                ->exists();

            if (!$isAssociated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este motorista não está associado a esta instituição'
                ], 422);
            }

            // Remover o motorista da instituição
            $institution->driverProfiles()->detach($driverProfileId);

            return response()->json([
                'success' => true,
                'message' => 'Motorista removido da instituição com sucesso'
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
                'message' => 'Erro ao remover motorista da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class VehicleController extends Controller
{
    /**
     * Obtém todos os veículos de uma instituição
     *
     * @param int $institutionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstitutionVehicles($institutionId)
    {
        try {
            $institution = Institution::query()->findOrFail($institutionId);

            // Assumindo que existe um relacionamento vehicles() no modelo Institution
            if (method_exists($institution, 'vehicles')) {
                $vehicles = $institution->vehicles()->get();
            } else {
                // Busca por veículos que possuem o institution_id correspondente
                $vehicles = Vehicle::where('institution_id', $institutionId)->get();
            }

            return response()->json([
                'success' => true,
                'vehicles' => $vehicles
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instituição não encontrada'
                ], 404);
            }

            Log::error('Erro ao buscar veículos da instituição: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar veículos da instituição',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um veículo específico
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $vehicle = Vehicle::query()->findOrFail($id);

            return response()->json([
                'success' => true,
                'vehicle' => $vehicle
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado'
                ], 404);
            }

            Log::error('Erro ao buscar veículo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Armazena um novo veículo
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'license_plate' => 'required|string|max:20|unique:vehicles,license_plate',
                'model' => 'required|string|max:100',
                'brand' => 'required|string|max:100',
                'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'capacity' => 'required|integer|min:1',
                'type' => 'required|string|max:50',
                'status' => 'required|string|in:active,maintenance,inactive',
                'institution_id' => 'required|exists:institutions,id',
                'features' => 'nullable|json',
                'last_maintenance' => 'nullable|date',
                'next_maintenance' => 'nullable|date|after:last_maintenance',
                'notes' => 'nullable|string',
            ]);

            $vehicle = new Vehicle();
            $vehicle->fill($validated);
            $vehicle->save();

            return response()->json([
                'success' => true,
                'vehicle' => $vehicle,
                'message' => 'Veículo criado com sucesso'
            ], 201);
        } catch (Exception $e) {
            Log::error('Erro ao criar veículo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um veículo existente
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $vehicle = Vehicle::query()->findOrFail($id);

            $validated = $request->validate([
                'license_plate' => 'sometimes|required|string|max:20|unique:vehicles,license_plate,' . $id,
                'model' => 'sometimes|required|string|max:100',
                'brand' => 'sometimes|required|string|max:100',
                'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
                'capacity' => 'sometimes|required|integer|min:1',
                'type' => 'sometimes|required|string|max:50',
                'status' => 'sometimes|required|string|in:active,maintenance,inactive',
                'institution_id' => 'sometimes|required|exists:institutions,id',
                'features' => 'nullable|json',
                'last_maintenance' => 'nullable|date',
                'next_maintenance' => 'nullable|date|after:last_maintenance',
                'notes' => 'nullable|string',
            ]);

            $vehicle->fill($validated);
            $vehicle->save();

            return response()->json([
                'success' => true,
                'vehicle' => $vehicle,
                'message' => 'Veículo atualizado com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado'
                ], 404);
            }

            Log::error('Erro ao atualizar veículo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um veículo
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $vehicle = Vehicle::query()->findOrFail($id);
            $vehicle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Veículo removido com sucesso'
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado'
                ], 404);
            }

            Log::error('Erro ao remover veículo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

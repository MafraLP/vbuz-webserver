<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Users\Profiles\DriverProfile;
use App\Models\Users\Profiles\ManagerProfile;
use App\Models\Users\Profiles\MonitorProfile;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function createStaff(Request $request)
    {
        $user = Auth::user();

        // Apenas admin ou gerente pode criar funcionários
        if (!$user->isAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Sem permissão para criar funcionários'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|in:manager,driver,monitor',
            'institution_ids' => 'required|array',
            'institution_ids.*' => 'exists:institutions,id',

            // Campos para motoristas
            'license_number' => 'required_if:role,driver|nullable|string',
            'license_type' => 'required_if:role,driver|nullable|string',
            'license_expiry' => 'required_if:role,driver|nullable|date',

            // Campos para gerentes
            'position' => 'required_if:role,manager|nullable|string',
            'department' => 'required_if:role,manager|nullable|string',
            'access_level' => 'required_if:role,manager|nullable|in:prefecture,department,institution',

            // Campos comuns para funcionários
            'hire_date' => 'nullable|date',
            'employment_type' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Se for gerente, verificar se tem permissão nas instituições informadas
        if ($user->isManager()) {
            foreach ($validatedData['institution_ids'] as $institutionId) {
                $institution = Institution::query()->findOrFail($institutionId);

                // Verificar se gerencia a instituição ou sua prefeitura
                $managesInstitution = $user->managerProfile->institutions()
                    ->where('institutions.id', $institution->id)
                    ->exists();

                $managesPrefecture = false;
                if ($institution->prefecture_id) {
                    $managesPrefecture = $user->managerProfile->institutions()
                        ->where('institutions.id', $institution->prefecture_id)
                        ->exists();
                }

                if (!$managesInstitution && !$managesPrefecture) {
                    return response()->json([
                        'message' => 'Sem permissão para criar funcionário na instituição ' . $institution->name
                    ], 403);
                }
            }
        }

        // Criar o usuário base
        $staffUser = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'],
            'role' => $validatedData['role'],
        ]);

        // Criar o perfil específico com base no papel
        switch ($validatedData['role']) {
            case 'driver':
                $profile = DriverProfile::create([
                    'user_id' => $staffUser->id,
                    'license_number' => $validatedData['license_number'],
                    'license_type' => $validatedData['license_type'],
                    'license_expiry' => $validatedData['license_expiry'],
                    'hire_date' => $validatedData['hire_date'] ?? now(),
                    'employment_type' => $validatedData['employment_type'] ?? 'full_time',
                    'notes' => $validatedData['notes'],
                ]);

                // Associar às instituições
                foreach ($validatedData['institution_ids'] as $institutionId) {
                    $profile->institutions()->attach($institutionId, [
                        'start_date' => now(),
                        'status' => 'active',
                    ]);
                }

                $staffUser->load('driverProfile.institutions');
                break;

            case 'manager':
                $profile = ManagerProfile::create([
                    'user_id' => $staffUser->id,
                    'position' => $validatedData['position'],
                    'department' => $validatedData['department'],
                    'hire_date' => $validatedData['hire_date'] ?? now(),
                    'access_level' => $validatedData['access_level'],
                    'notes' => $validatedData['notes'],
                ]);

                // Associar às instituições
                foreach ($validatedData['institution_ids'] as $institutionId) {
                    $profile->institutions()->attach($institutionId, [
                        'is_primary' => count($validatedData['institution_ids']) === 1, // É primária se for única
                        'permissions' => json_encode(['full_access' => true]),
                        'assignment_date' => now(),
                    ]);
                }

                $staffUser->load('managerProfile.institutions');
                break;

            // No UserController, dentro do método createStaff, adicionar ao switch:

            case 'monitor':
                $profile = MonitorProfile::create([
                    'user_id' => $staffUser->id,
                    'hire_date' => $validatedData['hire_date'] ?? now(),
                    'employment_type' => $validatedData['employment_type'] ?? 'full_time',
                    'status' => 'active',
                    'qualifications' => $request->input('qualifications'),
                    'background_check_date' => $request->input('background_check_date'),
                    'notes' => $validatedData['notes'],
                ]);

                // Associar às instituições
                foreach ($validatedData['institution_ids'] as $institutionId) {
                    $profile->institutions()->attach($institutionId, [
                        'start_date' => now(),
                        'status' => 'active',
                    ]);
                }

                $staffUser->load('monitorProfile.institutions');
                break;
        }

        return response()->json([
            'message' => 'Funcionário criado com sucesso',
            'user' => $staffUser
        ], 201);
    }

    public function getStaffByInstitution(Request $request, $institutionId)
    {
        $user = Auth::user();
        $institution = Institution::query()->findOrFail($institutionId);

        // Verificar permissão para ver funcionários da instituição
        if (!$this->canViewStaff($user, $institution)) {
            return response()->json(['message' => 'Sem permissão para visualizar funcionários desta instituição'], 403);
        }

        // Buscar diferentes tipos de funcionários associados à instituição
        $drivers = User::whereHas('driverProfile.institutions', function($query) use ($institutionId) {
            $query->where('institutions.id', $institutionId);
        })->where('role', 'driver')->with('driverProfile')->get();

        $managers = User::whereHas('managerProfile.institutions', function($query) use ($institutionId) {
            $query->where('institutions.id', $institutionId);
        })->where('role', 'manager')->with('managerProfile')->get();

        $monitors = User::whereHas('monitorProfile.institutions', function($query) use ($institutionId) {
            $query->where('institutions.id', $institutionId);
        })->where('role', 'monitor')->with('monitorProfile')->get();

        return response()->json([
            'drivers' => $drivers,
            'managers' => $managers,
            'monitors' => $monitors,
        ]);
    }

    // Função para verificar permissões
    private function canViewStaff($user, $institution)
    {
        // Admin pode ver tudo
        if ($user->isAdmin()) {
            return true;
        }

        // Gerentes podem ver funcionários de suas instituições
        if ($user->isManager()) {
            // Verifica se gerencia esta instituição
            $manages = $user->managerProfile->institutions()
                ->where('institutions.id', $institution->id)
                ->exists();

            // Ou se gerencia a prefeitura desta instituição
            $managesPrefecture = false;
            if ($institution->prefecture_id) {
                $managesPrefecture = $user->managerProfile->institutions()
                    ->where('institutions.id', $institution->prefecture_id)
                    ->exists();
            }

            return $manages || $managesPrefecture;
        }

        return false;
    }

    public function updateStaffAssignment(Request $request, $userId)
    {
        $adminUser = Auth::user();
        $staffUser = User::query()->findOrFail($userId);

        // Apenas admin ou gerente pode atualizar associações
        if (!$adminUser->isAdmin() && !$adminUser->isManager()) {
            return response()->json(['message' => 'Sem permissão para atualizar funcionários'], 403);
        }

        $validatedData = $request->validate([
            'institution_ids' => 'required|array',
            'institution_ids.*' => 'exists:institutions,id',
            'action' => 'required|in:add,remove,update',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Verifica permissões para as instituições
        if ($adminUser->isManager()) {
            foreach ($validatedData['institution_ids'] as $institutionId) {
                $institution = Institution::query()->findOrFail($institutionId);

                $managesInstitution = $adminUser->managerProfile->institutions()
                    ->where('institutions.id', $institution->id)
                    ->exists();

                $managesPrefecture = false;
                if ($institution->prefecture_id) {
                    $managesPrefecture = $adminUser->managerProfile->institutions()
                        ->where('institutions.id', $institution->prefecture_id)
                        ->exists();
                }

                if (!$managesInstitution && !$managesPrefecture) {
                    return response()->json([
                        'message' => 'Sem permissão para gerenciar instituição ' . $institution->name
                    ], 403);
                }
            }
        }

        // Atualizar associações conforme o papel
        switch ($staffUser->role) {
            case 'driver':
                $profile = $staffUser->driverProfile;
                if (!$profile) {
                    return response()->json(['message' => 'Perfil de motorista não encontrado'], 404);
                }

                if ($validatedData['action'] === 'add') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->attach($institutionId, [
                            'start_date' => $validatedData['start_date'] ?? now(),
                            'end_date' => $validatedData['end_date'],
                            'status' => $validatedData['status'] ?? 'active',
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                } elseif ($validatedData['action'] === 'remove') {
                    $profile->institutions()->detach($validatedData['institution_ids']);
                } elseif ($validatedData['action'] === 'update') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->updateExistingPivot($institutionId, [
                            'end_date' => $validatedData['end_date'],
                            'status' => $validatedData['status'],
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                }
                break;

            case 'manager':
                $profile = $staffUser->managerProfile;
                if (!$profile) {
                    return response()->json(['message' => 'Perfil de gerente não encontrado'], 404);
                }

                if ($validatedData['action'] === 'add') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->attach($institutionId, [
                            'is_primary' => $request->input('is_primary', false),
                            'permissions' => $request->input('permissions', json_encode(['basic_access' => true])),
                            'assignment_date' => now(),
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                } elseif ($validatedData['action'] === 'remove') {
                    $profile->institutions()->detach($validatedData['institution_ids']);
                } elseif ($validatedData['action'] === 'update') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->updateExistingPivot($institutionId, [
                            'is_primary' => $request->input('is_primary'),
                            'permissions' => $request->input('permissions'),
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                }
                break;

            // No UserController, adicionar ao switch no método updateStaffAssignment:

            case 'monitor':
                $profile = $staffUser->monitorProfile;
                if (!$profile) {
                    return response()->json(['message' => 'Perfil de monitor não encontrado'], 404);
                }

                if ($validatedData['action'] === 'add') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->attach($institutionId, [
                            'start_date' => $validatedData['start_date'] ?? now(),
                            'end_date' => $validatedData['end_date'],
                            'status' => $validatedData['status'] ?? 'active',
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                } elseif ($validatedData['action'] === 'remove') {
                    $profile->institutions()->detach($validatedData['institution_ids']);
                } elseif ($validatedData['action'] === 'update') {
                    foreach ($validatedData['institution_ids'] as $institutionId) {
                        $profile->institutions()->updateExistingPivot($institutionId, [
                            'end_date' => $validatedData['end_date'],
                            'status' => $validatedData['status'],
                            'notes' => $validatedData['notes'],
                        ]);
                    }
                }
                break;

            default:
                return response()->json(['message' => 'Tipo de funcionário não suportado para esta operação'], 400);
        }

        // Recarregar o usuário com suas associações atualizadas
        $staffUser->refresh();
        if ($staffUser->role === 'driver') {
            $staffUser->load('driverProfile.institutions');
        } elseif ($staffUser->role === 'manager') {
            $staffUser->load('managerProfile.institutions');
        }

        return response()->json([
            'message' => 'Associações atualizadas com sucesso',
            'user' => $staffUser
        ]);
    }

    public function profile()
    {
        $user = Auth::user();

        // Carregar relacionamentos apropriados com base no papel
        switch ($user->role) {
            case 'admin':
                // Admin não tem perfil adicional
                break;
            case 'manager':
                $user->load('managerProfile.institutions');
                break;
            case 'driver':
                $user->load('driverProfile.institutions');
                break;
            case 'monitor':
                $user->load('monitorProfile');
                break;
            case 'passenger':
                $user->load('passengerProfile.enrollments', 'passengerProfile.itineraries');
                break;
        }

        return response()->json(['user' => $user]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->
            json(['message' => 'Não autenticado'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'sometimes|string|min:8',

            // Campos específicos do perfil de passageiro
            'default_address' => 'nullable|string',
            'default_latitude' => 'nullable|numeric',
            'default_longitude' => 'nullable|numeric',
            'emergency_contact' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'special_needs' => 'nullable|string',
        ]);

        // Atualizar os dados básicos do usuário
        $user->fill([
            'name' => $validatedData['name'] ?? $user->name,
            'email' => $validatedData['email'] ?? $user->email,
            'phone' => $validatedData['phone'] ?? $user->phone,
        ]);

        if (isset($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
        }

        $user->save();

        // Se for passageiro, atualizar o perfil
        if ($user->isPassenger() && $user->passengerProfile) {
            $user->passengerProfile->update([
                'default_address' => $validatedData['default_address'] ?? $user->passengerProfile->default_address,
                'default_latitude' => $validatedData['default_latitude'] ?? $user->passengerProfile->default_latitude,
                'default_longitude' => $validatedData['default_longitude'] ?? $user->passengerProfile->default_longitude,
                'emergency_contact' => $validatedData['emergency_contact'] ?? $user->passengerProfile->emergency_contact,
                'emergency_phone' => $validatedData['emergency_phone'] ?? $user->passengerProfile->emergency_phone,
                'special_needs' => $validatedData['special_needs'] ?? $user->passengerProfile->special_needs,
            ]);
        }

        // Recarregar o usuário com o perfil atualizado
        $user->refresh();
        if ($user->isPassenger()) {
            $user->load('passengerProfile');
        }

        return response()->json([
            'message' => 'Perfil atualizado com sucesso',
            'user' => $user
        ]);
    }
}

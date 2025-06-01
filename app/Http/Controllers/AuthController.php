<?php
namespace App\Http\Controllers;

use App\Models\Users\User;
use App\Models\Users\Profiles\Users\Profiles\PassengerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'unknown');

        $token = $user->createToken($deviceName)->plainTextToken;

        // Carregamos os dados de perfil relevantes com base no papel do usuário
        switch ($user->role) {
            case 'driver':
                $user->load('driverProfile.institutions');
                break;
            case 'manager':
                $user->load('managerProfile.institutions');
                break;
            case 'monitor':
                $user->load('monitorProfile');
                break;
            case 'passenger':
                $user->load('passengerProfile.enrollments');
                break;
        }

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        // Carregar perfis específicos baseados na role
        switch ($user->role) {
            case 'driver':
                $user->load('driverProfile');
                break;
            case 'manager':
                $user->load('managerProfile');
                break;
            case 'monitor':
                $user->load('monitorProfile');
                break;
            case 'passenger':
                $user->load('passengerProfile');
                break;
        }

        // Adicionar instituições acessíveis usando o método do modelo
        $user->accessible_institutions = $user->getInstitutions();

        return response()->json([
            'user' => $user
        ]);
    }

    public function registerPassenger(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'emergency_contact' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'special_needs' => 'nullable|string',
        ]);

        // Criar o usuário base
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'passenger',
        ]);

        // Criar o perfil de passageiro
        $passengerProfile = PassengerProfile::create([
            'user_id' => $user->id,
            'default_address' => $request->address,
            'default_latitude' => $request->latitude,
            'default_longitude' => $request->longitude,
            'emergency_contact' => $request->emergency_contact,
            'emergency_phone' => $request->emergency_phone,
            'special_needs' => $request->special_needs,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $user->load('passengerProfile'),
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso']);
    }
}

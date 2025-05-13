<?php

use App\Http\Controllers\DriverController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\RoutePointController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/debug', function () {
    return response()->json(['status' => 'success', 'message' => 'Debug endpoint working']);
});

Route::post('/simple', function () {
    return response()->json([
        'success' => true,
        'message' => 'Rota simples funcionando'
    ]);
});

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('login', 'login');
    });

Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:9000')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('any', '.*');

Route::prefix('route')->group(function () {
    require 'route.php';
});

/*
|--------------------------------------------------------------------------
| API Routes for Institutions
|--------------------------------------------------------------------------
*/

// Rota para buscar todas as instituições do usuário atual
Route::get('/user/institutions', [InstitutionController::class, 'getUserInstitutions'])
    ->middleware('auth:sanctum');

// Rotas para CRUD de instituições
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/institutions/{id}', [InstitutionController::class, 'show']);
    Route::post('/institutions', [InstitutionController::class, 'store']);
    Route::put('/institutions/{id}', [InstitutionController::class, 'update']);
    Route::delete('/institutions/{id}', [InstitutionController::class, 'destroy']);

    // Rotas para gerenciar usuários de uma instituição
    Route::get('/institutions/{id}/users', [InstitutionController::class, 'getInstitutionUsers']);
    Route::post('/institutions/{id}/users', [InstitutionController::class, 'addUserToInstitution']);
    Route::delete('/institutions/{institutionId}/users/{userId}', [InstitutionController::class, 'removeUserFromInstitution']);
});

/*
|--------------------------------------------------------------------------
| API Routes para Rotas e Pontos
|--------------------------------------------------------------------------
*/

// Rotas para gerenciamento de rotas
Route::middleware('auth:sanctum')->group(function () {
    // Rotas do usuário
    Route::get('/routes', [RouteController::class, 'index']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);

    // Cálculo de rota
    Route::post('/routes/{id}/calculate', [RouteController::class, 'calculateRoute']);

    // Publicar/despublicar rota
    Route::post('/routes/{id}/publish', [RouteController::class, 'togglePublish']);

    // Pontos de uma rota
    Route::get('/routes/{routeId}/points', [RoutePointController::class, 'getRoutePoints']);

    // CRUD de pontos
    Route::post('/points', [RoutePointController::class, 'store']);
    Route::get('/points/{id}', [RoutePointController::class, 'show']);
    Route::put('/points/{id}', [RoutePointController::class, 'update']);
    Route::delete('/points/{id}', [RoutePointController::class, 'destroy']);
});

// Rotas públicas (não requerem autenticação)
Route::get('/public/routes', [RouteController::class, 'publicRoutes']);
Route::get('/public/routes/nearby', [RouteController::class, 'nearby']);


// Rotas de Pontos
Route::middleware('auth:sanctum')->group(function () {
    // Pontos de uma instituição
    Route::get('/institutions/{institutionId}/points', [RoutePointController::class, 'getInstitutionPoints']);

    // CRUD de pontos
    Route::get('/points/{id}', [RoutePointController::class, 'show']);
    Route::post('/points', [RoutePointController::class, 'store']);
    Route::put('/points/{id}', [RoutePointController::class, 'update']);
    Route::delete('/points/{id}', [RoutePointController::class, 'destroy']);
});

// Rotas de Rotas
Route::middleware('auth:sanctum')->group(function () {
    // Rotas de uma instituição
    Route::get('/institutions/{institutionId}/routes', [RouteController::class, 'getInstitutionRoutes']);

    // CRUD de rotas
    Route::get('/routes/{id}', [RouteController::class, 'show']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
});

// Rotas de Motoristas
Route::middleware('auth:sanctum')->group(function () {
    // Motoristas de uma instituição
    Route::get('/institutions/{institutionId}/drivers', [DriverController::class, 'getInstitutionDrivers']);

    // Gerenciamento de relações entre motoristas e instituições
    Route::post('/institutions/{institutionId}/drivers', [DriverController::class, 'addDriverToInstitution']);
    Route::delete('/institutions/{institutionId}/drivers/{driverProfileId}', [DriverController::class, 'removeDriverFromInstitution']);

    // Detalhes de um motorista específico
    Route::get('/drivers/{id}', [DriverController::class, 'show']);
});

// Rotas de Veículos
Route::middleware('auth:sanctum')->group(function () {
    // Veículos de uma instituição
    Route::get('/institutions/{institutionId}/vehicles', [VehicleController::class, 'getInstitutionVehicles']);

    // CRUD de veículos
    Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Pontos de uma instituição (incluindo ancestrais)
    Route::get('/institutions/{institutionId}/points', [PointController::class, 'getInstitutionPoints']);

    // CRUD de pontos
    Route::get('/points/{id}', [PointController::class, 'show']);
    Route::post('/points', [PointController::class, 'store']);
    Route::put('/points/{id}', [PointController::class, 'update']);
    Route::delete('/points/{id}', [PointController::class, 'destroy']);

    // Pontos próximos a uma localização
    Route::get('/points/nearby', [PointController::class, 'nearbyPoints']);

    // Estatísticas de uso de pontos
    Route::get('/institutions/{institutionId}/points/stats', [PointController::class, 'getPointsUsageStats']);
});

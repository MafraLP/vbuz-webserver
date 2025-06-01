<?php

namespace App\Jobs;

use App\Models\Routes\Route;
use App\Services\RouteCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class CalculateRouteSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $routeId;

    // Timeout reduzido para rotas pequenas
    public $timeout = 60; // 1 minuto
    public $tries = 2; // Menos tentativas
    public $maxExceptions = 1; // Falhar mais rápido

    public function __construct(Route $route)
    {
        $this->routeId = $route->id;
    }

    public function handle(RouteCalculationService $calculationService)
    {
        $startTime = microtime(true);

        try {
            Log::info("Job OTIMIZADO iniciado para rota {$this->routeId}");

            $route = Route::with('points')->find($this->routeId);

            if (!$route) {
                Log::error("Rota {$this->routeId} não encontrada");
                return;
            }

            if ($route->calculation_status !== 'calculating') {
                Log::info("Rota {$this->routeId} não está calculando, abortando");
                return;
            }

            // Executar cálculo otimizado
            $calculationService->calculateRouteSegments($route);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Job OTIMIZADO concluído para rota {$route->id} em {$executionTime}ms");

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error("Erro no job OTIMIZADO da rota {$this->routeId} após {$executionTime}ms: " . $e->getMessage());

            $route = Route::find($this->routeId);
            if ($route) {
                $route->update([
                    'calculation_status' => 'error',
                    'calculation_error' => $e->getMessage()
                ]);
            }

            throw $e;
        }
    }

    public function failed(Exception $exception)
    {
        Log::error("Job OTIMIZADO falhou definitivamente para rota {$this->routeId}: " . $exception->getMessage());

        $route = Route::find($this->routeId);
        if ($route) {
            $route->update([
                'calculation_status' => 'failed',
                'calculation_error' => $exception->getMessage()
            ]);
        }
    }
}

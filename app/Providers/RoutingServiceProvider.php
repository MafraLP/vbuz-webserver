<?php

namespace App\Providers;

use App\Services\RouteCalculationService;
use Illuminate\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(RouteCalculationService::class, function ($app) {
            return new RouteCalculationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

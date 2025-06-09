<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuração do Serviço de Roteamento
    |--------------------------------------------------------------------------
    |
    | Aqui você pode configurar qual serviço de roteamento usar:
    | - false: Usar OSRM local (padrão para desenvolvimento)
    | - true: Usar API externa (OpenRouteService para produção)
    |
    */

    'use_external_api' => env('ROUTING_USE_EXTERNAL_API', false),

    /*
    |--------------------------------------------------------------------------
    | OSRM Local
    |--------------------------------------------------------------------------
    |
    | Configurações para usar OSRM rodando localmente
    |
    */

    'osrm_url' => env('OSRM_URL', 'http://localhost:5000'),

    /*
    |--------------------------------------------------------------------------
    | OpenRouteService API
    |--------------------------------------------------------------------------
    |
    | Configurações para usar a API externa do OpenRouteService
    | Obtenha sua chave em: https://openrouteservice.org/dev/#/signup
    |
    */
    'openroute_service_api_key' => env('OPENROUTE_SERVICE_API_KEY', null),


    /*
    |--------------------------------------------------------------------------
    | Configurações de Performance
    |--------------------------------------------------------------------------
    */

    'request_timeout' => env('ROUTING_REQUEST_TIMEOUT', 30),
    'max_retries' => env('ROUTING_MAX_RETRIES', 2),
    'rate_limit_per_minute' => env('ROUTING_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Debug e Logs
    |--------------------------------------------------------------------------
    */

    'debug_mode' => env('ROUTING_DEBUG', false),
    'log_requests' => env('ROUTING_LOG_REQUESTS', true),
];

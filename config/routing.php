<?php

return [
    'provider' => env('ROUTING_PROVIDER', 'openrouteservice'),
    // Configurações OSRM
    'osrm' => [
        'enabled' => env('OSRM_ENABLED', false),
        'base_url' => env('OSRM_BASE_URL', 'http://osrm-backend:5000'),
        'timeout' => 2, // Timeout baixo para servidor local
        'retries' => 2,
        'retry_delay' => 100, // ms
    ],


    // Configurações OpenRouteService (fallback)
    'openrouteservice' => [
        'timeout' => 5,
        'retries' => 2,
        'retry_delay' => 500, // ms
    ],

    // Configurações gerais
    'default_profile' => 'driving-car',
    'batch_size' => 10,
    'cache_duration' => 30 * 24 * 60 * 60, // 30 dias
    'parallel_threshold' => 10,
    'simplify_geometry' => true,
];

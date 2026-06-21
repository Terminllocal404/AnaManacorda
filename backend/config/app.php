<?php

declare(strict_types=1);

/**
 * Configuracao geral da aplicacao.
 */
return [
    'name'        => env('APP_NAME', 'Ana Manacorda'),
    'env'         => env('APP_ENV', 'production'),
    'debug'       => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url'         => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'frontend_url'=> rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),
    'timezone'    => 'America/Sao_Paulo',

    'session' => [
        'name'     => env('SESSION_NAME', 'ana_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure'   => filter_var(env('SESSION_SECURE', 'true'), FILTER_VALIDATE_BOOL),
    ],

    'rate_limit' => [
        'max'    => (int) env('RATE_LIMIT_MAX', 120),
        'window' => (int) env('RATE_LIMIT_WINDOW', 60),
    ],

    'entrega' => [
        'cidade' => env('ENTREGA_CIDADE', 'Juiz de Fora'),
        'estado' => env('ENTREGA_ESTADO', 'MG'),
    ],
];

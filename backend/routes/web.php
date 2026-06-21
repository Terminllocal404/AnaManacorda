<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;

/**
 * Rotas "web" (fora do prefixo /api).
 *
 * A aplicacao e, na pratica, uma API REST consumida pelo frontend ja existente.
 * Estas rotas servem apenas para verificacao de status e diagnostico.
 */
return static function (Router $router): void {
    $router->get('/', static function (): void {
        Response::success('API Ana Manacorda operacional.', [
            'app'    => config('app.name'),
            'versao' => '1.0.0',
        ]);
    });

    $router->get('/health', static function (): void {
        Response::success('OK', [
            'status'    => 'up',
            'timestamp' => now(),
        ]);
    });
};

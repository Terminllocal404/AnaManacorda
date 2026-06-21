<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;

/**
 * Define os cabecalhos CORS para o frontend autorizado.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        $allowedOrigin = (string) config('app.frontend_url');

        if (headers_sent()) {
            return;
        }

        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}

<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Exceptions\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Helpers\Auth;

/**
 * Garante que o usuario autenticado seja administrador.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            throw new HttpException('Autenticacao necessaria.', 401);
        }
        if (!Auth::isAdmin()) {
            throw new HttpException('Acesso restrito a administradores.', 403);
        }
    }
}

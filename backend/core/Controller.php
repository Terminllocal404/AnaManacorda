<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller base. Disponibiliza atalhos de resposta JSON.
 */
abstract class Controller
{
    protected function success(string $message, mixed $data = null, int $status = 200): void
    {
        Response::success($message, $data, $status);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): void
    {
        Response::error($message, $status, $errors);
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrato dos middlewares. Devem lancar HttpException para interromper.
 */
interface MiddlewareInterface
{
    public function handle(Request $request): void;
}

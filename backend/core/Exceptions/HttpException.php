<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Excecao que carrega um status HTTP para resposta da API.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }
}

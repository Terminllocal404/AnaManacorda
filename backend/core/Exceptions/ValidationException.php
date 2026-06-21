<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Erros de validacao de entrada (HTTP 422).
 */
class ValidationException extends HttpException
{
    /**
     * @param array<string,string[]> $errors
     */
    public function __construct(
        private array $errors,
        string $message = 'Dados invalidos.'
    ) {
        parent::__construct($message, 422);
    }

    /** @return array<string,string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

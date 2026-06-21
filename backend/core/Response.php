<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respostas JSON padronizadas da API.
 */
final class Response
{
    /**
     * @param array<string,mixed>|list<mixed>|null $data
     */
    public static function json(bool $success, string $message, mixed $data = null, int $status = 200, ?array $extra = null): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = ['success' => $success, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($extra !== null) {
            $payload = array_merge($payload, $extra);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function success(string $message, mixed $data = null, int $status = 200): void
    {
        self::json(true, $message, $data, $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): void
    {
        $extra = $errors !== null ? ['errors' => $errors] : null;
        self::json(false, $message, null, $status, $extra);
    }
}

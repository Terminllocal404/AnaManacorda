<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsula a requisicao HTTP de entrada.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,string> */
    private array $headers;
    /** @var array<string,string> */
    private array $params = [];
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path    = $this->resolvePath();
        $this->query   = $_GET;
        $this->headers = $this->resolveHeaders();
        $this->body    = $this->resolveBody();
    }

    private function resolvePath(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return array<string,string> */
    private function resolveHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    /** @return array<string,mixed> */
    private function resolveBody(): array
    {
        $contentType = $this->header('Content-Type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
        return $this->headers[$name] ?? null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Retorna apenas as chaves informadas.
     * @param string[] $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = $all[$key];
            }
        }
        return $out;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** @param array<string,string> $params */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_CLIENT_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate) {
                $ip = trim(explode(',', (string) $candidate)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}

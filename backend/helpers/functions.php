<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

if (!function_exists('base_path')) {
    /** Caminho absoluto a partir da raiz do projeto. */
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__);
        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}

if (!function_exists('env')) {
    /** Le uma variavel de ambiente carregada do arquivo .env. */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /** Le um valor de configuracao usando notacao por ponto: config('app.name'). */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escapa uma string para saida segura (protecao XSS). */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}

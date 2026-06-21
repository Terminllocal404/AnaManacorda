<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acesso aos arquivos de configuracao em /config usando notacao por ponto.
 */
final class Config
{
    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(self::$cache[$file])) {
            $path = base_path('config/' . $file . '.php');
            self::$cache[$file] = is_file($path) ? (require $path) : [];
        }

        $value = self::$cache[$file];
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }
}

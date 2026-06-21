<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader PSR-4 proprio.
 *
 * Garante o carregamento das classes do namespace App\* mesmo na ausencia do
 * Composer. As bibliotecas externas (PHPMailer e o SDK do Mercado Pago) continuam
 * sendo carregadas pelo vendor/autoload.php quando presente.
 */
final class Autoload
{
    /** @var array<string,string> prefixo PSR-4 => diretorio base absoluto */
    private static array $prefixes = [];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $root = dirname(__DIR__);

        self::$prefixes = [
            'App\\Core\\'         => $root . '/core/',
            'App\\Helpers\\'      => $root . '/helpers/',
            'App\\Middlewares\\'  => $root . '/middlewares/',
            'App\\Models\\'       => $root . '/models/',
            'App\\Repositories\\' => $root . '/repositories/',
            'App\\Services\\'     => $root . '/services/',
            'App\\Controllers\\'  => $root . '/controllers/',
        ];

        spl_autoload_register([self::class, 'load']);
        self::$registered = true;
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}

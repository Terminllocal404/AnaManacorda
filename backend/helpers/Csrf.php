<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Request;

/**
 * Protecao CSRF baseada em token de sessao (envio via header X-CSRF-Token).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(Request $request): bool
    {
        Auth::start();
        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        $provided = $request->header('X-CSRF-Token')
            ?? (is_string($request->input('_csrf')) ? $request->input('_csrf') : null);

        return is_string($provided) && hash_equals($stored, $provided);
    }
}

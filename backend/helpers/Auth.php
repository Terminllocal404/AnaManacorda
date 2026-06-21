<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Gerenciamento de sessao do usuario autenticado.
 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $session = config('app.session');
        session_name((string) $session['name']);
        session_set_cookie_params([
            'lifetime' => (int) $session['lifetime'],
            'path'     => '/',
            'secure'   => (bool) $session['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** @param array<string,mixed> $usuario */
    public static function login(array $usuario): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $usuario['id'];
        $_SESSION['role']    = (string) ($usuario['role'] ?? 'cliente');
        $_SESSION['nome']    = (string) ($usuario['nome'] ?? '');
        $_SESSION['email']   = (string) ($usuario['email'] ?? '');
        $_SESSION['logged_at'] = time();
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        self::start();
        return $_SESSION['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        self::start();
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'    => (int) $_SESSION['user_id'],
            'role'  => $_SESSION['role'] ?? 'cliente',
            'nome'  => $_SESSION['nome'] ?? '',
            'email' => $_SESSION['email'] ?? '',
        ];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}

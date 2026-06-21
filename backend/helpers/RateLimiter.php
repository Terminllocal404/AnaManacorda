<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Rate limiting persistido em arquivo (token bucket por chave).
 */
final class RateLimiter
{
    private static function dir(): string
    {
        $dir = storage_path('cache/ratelimit');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Registra um acesso. Retorna ['allowed' => bool, 'remaining' => int, 'retry_after' => int].
     *
     * @return array{allowed:bool,remaining:int,retry_after:int}
     */
    public static function hit(string $key, int $max, int $window): array
    {
        // Derivacao do nome do arquivo com SHA-256 (sem SHA1 em componente de seguranca).
        $file = self::dir() . '/' . hash('sha256', $key) . '.json';
        $now  = time();

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];
        }

        flock($handle, LOCK_EX);
        $raw  = stream_get_contents($handle) ?: '';
        $data = json_decode($raw, true);

        if (!is_array($data) || ($data['reset'] ?? 0) < $now) {
            $data = ['count' => 0, 'reset' => $now + $window];
        }

        $data['count']++;
        $allowed   = $data['count'] <= $max;
        $remaining = max(0, $max - $data['count']);
        $retry     = $allowed ? 0 : max(1, $data['reset'] - $now);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['allowed' => $allowed, 'remaining' => $remaining, 'retry_after' => $retry];
    }
}

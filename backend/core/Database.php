<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexao PDO unica (singleton) com o MySQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        /** @var array<string,mixed> $cfg */
        $cfg = config('database');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        } catch (PDOException $e) {
            throw new RuntimeException('Falha ao conectar ao banco de dados: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** Permite injetar uma conexao (usado em testes). */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function beginTransaction(): void
    {
        $pdo = self::connection();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    public static function commit(): void
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    public static function rollBack(): void
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

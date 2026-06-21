<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Base com acesso ao PDO e atalhos seguros (prepared statements).
 */
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::connection();
    }

    /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
    protected function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params @return array<int,array<string,mixed>> */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param array<int|string,mixed> $params */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** @param array<int|string,mixed> $params */
    protected function insertGetId(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->db->lastInsertId();
    }
}

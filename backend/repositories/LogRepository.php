<?php

declare(strict_types=1);

namespace App\Repositories;

final class LogRepository extends BaseRepository
{
    /**
     * Registra um log de sistema.
     *
     * @param array<string,mixed>|null $contexto
     */
    public function registrar(
        string $acao,
        ?int $usuarioId,
        string $ip,
        string $nivel = 'info',
        ?string $descricao = null,
        ?array $contexto = null
    ): void {
        $this->execute(
            'INSERT INTO logs_sistema (usuario_id, acao, nivel, descricao, ip, contexto)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $usuarioId,
                $acao,
                $nivel,
                $descricao,
                $ip,
                $contexto !== null ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listarPorUsuario(int $usuarioId, int $limite = 50): array
    {
        $limite = max(1, min(200, $limite));
        return $this->fetchAll(
            "SELECT acao, nivel, descricao, ip, created_at
               FROM logs_sistema
              WHERE usuario_id = ?
              ORDER BY id DESC
              LIMIT {$limite}",
            [$usuarioId]
        );
    }
}

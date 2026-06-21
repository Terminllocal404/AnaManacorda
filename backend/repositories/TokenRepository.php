<?php

declare(strict_types=1);

namespace App\Repositories;

final class TokenRepository extends BaseRepository
{
    // ---------------- Verificacao de e-mail ----------------

    public function criarTokenVerificacao(int $usuarioId, string $token, string $expiraEm): int
    {
        $this->execute('DELETE FROM tokens_verificacao WHERE usuario_id = ?', [$usuarioId]);
        return $this->insertGetId(
            'INSERT INTO tokens_verificacao (usuario_id, token, expira_em) VALUES (?, ?, ?)',
            [$usuarioId, $token, $expiraEm]
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarTokenVerificacao(string $token): ?array
    {
        return $this->fetch(
            'SELECT * FROM tokens_verificacao WHERE token = ? AND usado = 0',
            [$token]
        );
    }

    public function marcarVerificacaoUsado(int $id): void
    {
        $this->execute('UPDATE tokens_verificacao SET usado = 1 WHERE id = ?', [$id]);
    }

    // ---------------- Recuperacao de senha ----------------

    public function criarTokenRecuperacao(int $usuarioId, string $codigoHash, string $expiraEm): int
    {
        $this->execute(
            'UPDATE tokens_recuperacao SET usado = 1 WHERE usuario_id = ? AND usado = 0',
            [$usuarioId]
        );
        return $this->insertGetId(
            'INSERT INTO tokens_recuperacao (usuario_id, codigo_hash, expira_em, tentativas)
             VALUES (?, ?, ?, 0)',
            [$usuarioId, $codigoHash, $expiraEm]
        );
    }

    /** @return array<string,mixed>|null */
    public function recuperacaoAtivaPorUsuario(int $usuarioId): ?array
    {
        return $this->fetch(
            'SELECT * FROM tokens_recuperacao
              WHERE usuario_id = ? AND usado = 0
              ORDER BY id DESC LIMIT 1',
            [$usuarioId]
        );
    }

    public function incrementarTentativas(int $id): void
    {
        $this->execute('UPDATE tokens_recuperacao SET tentativas = tentativas + 1 WHERE id = ?', [$id]);
    }

    public function marcarRecuperacaoUsado(int $id): void
    {
        $this->execute('UPDATE tokens_recuperacao SET usado = 1 WHERE id = ?', [$id]);
    }
}

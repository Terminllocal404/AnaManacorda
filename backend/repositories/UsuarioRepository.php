<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Usuario;

final class UsuarioRepository extends BaseRepository
{
    public function buscarPorId(int $id): ?Usuario
    {
        $row = $this->fetch('SELECT * FROM usuarios WHERE id = ?', [$id]);
        return $row ? Usuario::fromRow($row) : null;
    }

    public function buscarPorEmail(string $email): ?Usuario
    {
        $row = $this->fetch('SELECT * FROM usuarios WHERE email = ?', [strtolower($email)]);
        return $row ? Usuario::fromRow($row) : null;
    }

    public function emailExiste(string $email, ?int $exceto = null): bool
    {
        if ($exceto !== null) {
            $row = $this->fetch('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [strtolower($email), $exceto]);
        } else {
            $row = $this->fetch('SELECT id FROM usuarios WHERE email = ?', [strtolower($email)]);
        }
        return $row !== null;
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO usuarios (nome, sobrenome, email, telefone, senha_hash, email_verificado, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['nome'],
                $dados['sobrenome'],
                strtolower((string) $dados['email']),
                $dados['telefone'] ?? null,
                $dados['senha_hash'],
                (int) ($dados['email_verificado'] ?? 0),
                $dados['role'] ?? 'cliente',
                $dados['status'] ?? 'pendente',
            ]
        );
    }

    public function atualizarPerfil(int $id, string $nome, string $sobrenome, ?string $telefone): void
    {
        $this->execute(
            'UPDATE usuarios SET nome = ?, sobrenome = ?, telefone = ?, updated_at = NOW() WHERE id = ?',
            [$nome, $sobrenome, $telefone, $id]
        );
    }

    public function atualizarSenha(int $id, string $hash): void
    {
        $this->execute('UPDATE usuarios SET senha_hash = ?, updated_at = NOW() WHERE id = ?', [$hash, $id]);
    }

    public function marcarEmailVerificado(int $id): void
    {
        $this->execute(
            "UPDATE usuarios SET email_verificado = 1, status = 'ativo', updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Endereco;

final class EnderecoRepository extends BaseRepository
{
    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO enderecos
                (usuario_id, nome, sobrenome, telefone, cep, rua, numero, bairro, complemento, cidade, estado, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['usuario_id'],
                $dados['nome'],
                $dados['sobrenome'],
                $dados['telefone'],
                $dados['cep'],
                $dados['rua'],
                $dados['numero'],
                $dados['bairro'],
                $dados['complemento'] ?? null,
                $dados['cidade'],
                $dados['estado'],
                $dados['observacoes'] ?? null,
            ]
        );
    }

    public function buscarPorId(int $id): ?Endereco
    {
        $row = $this->fetch('SELECT * FROM enderecos WHERE id = ?', [$id]);
        return $row ? Endereco::fromRow($row) : null;
    }

    public function buscarPorIdEUsuario(int $id, int $usuarioId): ?Endereco
    {
        $row = $this->fetch('SELECT * FROM enderecos WHERE id = ? AND usuario_id = ?', [$id, $usuarioId]);
        return $row ? Endereco::fromRow($row) : null;
    }

    /** @return Endereco[] */
    public function listarPorUsuario(int $usuarioId): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY id DESC',
            [$usuarioId]
        );
        return array_map(static fn (array $row) => Endereco::fromRow($row), $rows);
    }
}

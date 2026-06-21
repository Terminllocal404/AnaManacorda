<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Categoria;

final class CategoriaRepository extends BaseRepository
{
    /** @return Categoria[] */
    public function listar(bool $somenteAtivas = true): array
    {
        $sql = 'SELECT * FROM categorias';
        if ($somenteAtivas) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        return array_map([Categoria::class, 'fromRow'], $this->fetchAll($sql));
    }

    public function buscarPorId(int $id): ?Categoria
    {
        $row = $this->fetch('SELECT * FROM categorias WHERE id = ?', [$id]);
        return $row ? Categoria::fromRow($row) : null;
    }

    public function buscarPorSlug(string $slug): ?Categoria
    {
        $row = $this->fetch('SELECT * FROM categorias WHERE slug = ?', [$slug]);
        return $row ? Categoria::fromRow($row) : null;
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO categorias (nome, slug, descricao, ativo) VALUES (?, ?, ?, ?)',
            [$dados['nome'], $dados['slug'], $dados['descricao'] ?? null, (int) ($dados['ativo'] ?? 1)]
        );
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(int $id, array $dados): void
    {
        $this->execute(
            'UPDATE categorias SET nome = ?, slug = ?, descricao = ?, ativo = ?, updated_at = NOW() WHERE id = ?',
            [$dados['nome'], $dados['slug'], $dados['descricao'] ?? null, (int) ($dados['ativo'] ?? 1), $id]
        );
    }

    public function excluir(int $id): void
    {
        $this->execute('DELETE FROM categorias WHERE id = ?', [$id]);
    }
}

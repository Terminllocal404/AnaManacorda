<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Produto;

final class ProdutoRepository extends BaseRepository
{
    /**
     * Lista produtos com filtros opcionais e paginacao.
     *
     * @param array{categoria_id?:int,categoria_slug?:string,busca?:string,somente_ativos?:bool,pagina?:int,por_pagina?:int} $filtros
     * @return array{itens:Produto[],total:int,pagina:int,por_pagina:int,total_paginas:int}
     */
    public function listar(array $filtros = []): array
    {
        $where  = [];
        $params = [];

        $somenteAtivos = $filtros['somente_ativos'] ?? true;
        if ($somenteAtivos) {
            $where[] = 'p.ativo = 1';
        }

        if (!empty($filtros['categoria_id'])) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['categoria_slug'])) {
            $where[]  = 'c.slug = ?';
            $params[] = (string) $filtros['categoria_slug'];
        }

        if (!empty($filtros['busca'])) {
            $where[]  = '(p.nome LIKE ? OR p.descricao LIKE ?)';
            $termo    = '%' . $filtros['busca'] . '%';
            $params[] = $termo;
            $params[] = $termo;
        }

        $clausula = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $totalRow = $this->fetch(
            "SELECT COUNT(*) AS total
               FROM produtos p
               LEFT JOIN categorias c ON c.id = p.categoria_id
               {$clausula}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $pagina    = max(1, (int) ($filtros['pagina'] ?? 1));
        $porPagina = (int) ($filtros['por_pagina'] ?? 12);
        $porPagina = max(1, min(60, $porPagina));
        $offset    = ($pagina - 1) * $porPagina;

        $rows = $this->fetchAll(
            "SELECT p.*, c.nome AS categoria_nome
               FROM produtos p
               LEFT JOIN categorias c ON c.id = p.categoria_id
               {$clausula}
               ORDER BY p.created_at DESC, p.id DESC
               LIMIT {$porPagina} OFFSET {$offset}",
            $params
        );

        $itens = [];
        foreach ($rows as $row) {
            $produto = Produto::fromRow($row);
            if ($produto->id !== null) {
                $produto->imagens = $this->carregarImagens($produto->id);
            }
            $itens[] = $produto;
        }

        return [
            'itens'         => $itens,
            'total'         => $total,
            'pagina'        => $pagina,
            'por_pagina'    => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ];
    }

    public function buscarPorId(int $id, bool $somenteAtivo = false): ?Produto
    {
        $sql = 'SELECT p.*, c.nome AS categoria_nome
                  FROM produtos p
                  LEFT JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.id = ?';
        if ($somenteAtivo) {
            $sql .= ' AND p.ativo = 1';
        }

        $row = $this->fetch($sql, [$id]);
        if ($row === null) {
            return null;
        }

        $produto = Produto::fromRow($row);
        $produto->imagens = $this->carregarImagens($id);
        return $produto;
    }

    public function buscarPorSlug(string $slug, bool $somenteAtivo = true): ?Produto
    {
        $sql = 'SELECT p.*, c.nome AS categoria_nome
                  FROM produtos p
                  LEFT JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.slug = ?';
        if ($somenteAtivo) {
            $sql .= ' AND p.ativo = 1';
        }

        $row = $this->fetch($sql, [$slug]);
        if ($row === null) {
            return null;
        }

        $produto = Produto::fromRow($row);
        if ($produto->id !== null) {
            $produto->imagens = $this->carregarImagens($produto->id);
        }
        return $produto;
    }

    /**
     * Produtos relacionados pela mesma categoria (exclui o proprio produto).
     *
     * @return Produto[]
     */
    public function relacionados(int $produtoId, int $categoriaId, int $limite = 4): array
    {
        $limite = max(1, min(12, $limite));
        $rows = $this->fetchAll(
            "SELECT p.*, c.nome AS categoria_nome
               FROM produtos p
               LEFT JOIN categorias c ON c.id = p.categoria_id
              WHERE p.categoria_id = ? AND p.id <> ? AND p.ativo = 1
              ORDER BY p.created_at DESC
              LIMIT {$limite}",
            [$categoriaId, $produtoId]
        );

        $itens = [];
        foreach ($rows as $row) {
            $produto = Produto::fromRow($row);
            if ($produto->id !== null) {
                $produto->imagens = $this->carregarImagens($produto->id);
            }
            $itens[] = $produto;
        }
        return $itens;
    }

    /** @return array<int,array<string,mixed>> */
    public function carregarImagens(int $produtoId): array
    {
        $rows = $this->fetchAll(
            'SELECT id, url, principal, ordem
               FROM produto_imagens
              WHERE produto_id = ?
              ORDER BY principal DESC, ordem ASC, id ASC',
            [$produtoId]
        );

        return array_map(static function (array $row): array {
            return [
                'id'        => (int) $row['id'],
                'url'       => (string) $row['url'],
                'principal' => (bool) $row['principal'],
                'ordem'     => (int) $row['ordem'],
            ];
        }, $rows);
    }

    /** Bloqueia a linha do produto para leitura consistente dentro de transacao. */
    public function lockPorId(int $id): ?Produto
    {
        $row = $this->fetch('SELECT * FROM produtos WHERE id = ? FOR UPDATE', [$id]);
        return $row ? Produto::fromRow($row) : null;
    }

    /** Decrementa estoque de forma segura (nao permite negativar). Retorna true se aplicado. */
    public function baixarEstoque(int $id, int $quantidade): bool
    {
        $linhas = $this->execute(
            'UPDATE produtos SET estoque = estoque - ?, updated_at = NOW()
              WHERE id = ? AND estoque >= ?',
            [$quantidade, $id, $quantidade]
        );
        return $linhas === 1;
    }

    public function devolverEstoque(int $id, int $quantidade): void
    {
        $this->execute(
            'UPDATE produtos SET estoque = estoque + ?, updated_at = NOW() WHERE id = ?',
            [$quantidade, $id]
        );
    }

    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO produtos (categoria_id, nome, slug, descricao, preco, estoque, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['categoria_id'] ?? null,
                $dados['nome'],
                $dados['slug'],
                $dados['descricao'] ?? null,
                $dados['preco'],
                (int) ($dados['estoque'] ?? 0),
                (int) ($dados['ativo'] ?? 1),
            ]
        );
    }

    /** @param array<string,mixed> $dados */
    public function atualizar(int $id, array $dados): void
    {
        $this->execute(
            'UPDATE produtos
                SET categoria_id = ?, nome = ?, slug = ?, descricao = ?, preco = ?, estoque = ?, ativo = ?, updated_at = NOW()
              WHERE id = ?',
            [
                $dados['categoria_id'] ?? null,
                $dados['nome'],
                $dados['slug'],
                $dados['descricao'] ?? null,
                $dados['preco'],
                (int) ($dados['estoque'] ?? 0),
                (int) ($dados['ativo'] ?? 1),
                $id,
            ]
        );
    }

    public function desativar(int $id): void
    {
        $this->execute('UPDATE produtos SET ativo = 0, updated_at = NOW() WHERE id = ?', [$id]);
    }

    public function adicionarImagem(int $produtoId, string $url, bool $principal = false, int $ordem = 0): int
    {
        return $this->insertGetId(
            'INSERT INTO produto_imagens (produto_id, url, principal, ordem) VALUES (?, ?, ?, ?)',
            [$produtoId, $url, (int) $principal, $ordem]
        );
    }

    public function removerImagem(int $imagemId, int $produtoId): void
    {
        $this->execute(
            'DELETE FROM produto_imagens WHERE id = ? AND produto_id = ?',
            [$imagemId, $produtoId]
        );
    }
}

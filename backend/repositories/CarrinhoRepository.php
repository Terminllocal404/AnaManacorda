<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Carrinho;
use App\Models\CarrinhoItem;

final class CarrinhoRepository extends BaseRepository
{
    /** Retorna o carrinho do usuario, criando se nao existir. */
    public function obterOuCriar(int $usuarioId): Carrinho
    {
        $carrinho = $this->buscarPorUsuario($usuarioId);
        if ($carrinho !== null) {
            return $carrinho;
        }

        $id = $this->insertGetId(
            'INSERT INTO carrinho (usuario_id) VALUES (?)',
            [$usuarioId]
        );

        $row = $this->fetch('SELECT * FROM carrinho WHERE id = ?', [$id]);
        /** @var array<string,mixed> $row */
        return Carrinho::fromRow($row);
    }

    public function buscarPorUsuario(int $usuarioId): ?Carrinho
    {
        $row = $this->fetch('SELECT * FROM carrinho WHERE usuario_id = ?', [$usuarioId]);
        if ($row === null) {
            return null;
        }

        $carrinho = Carrinho::fromRow($row);
        if ($carrinho->id !== null) {
            $carrinho->itens = $this->itens($carrinho->id);
            $carrinho->recalcular();
        }
        return $carrinho;
    }

    /** @return CarrinhoItem[] */
    public function itens(int $carrinhoId): array
    {
        $rows = $this->fetchAll(
            'SELECT ci.id, ci.carrinho_id, ci.produto_id, ci.quantidade, ci.preco_unitario,
                    p.nome AS produto_nome, p.slug AS produto_slug,
                    p.estoque AS estoque_disponivel, p.ativo AS produto_ativo
               FROM carrinho_itens ci
               INNER JOIN produtos p ON p.id = ci.produto_id
              WHERE ci.carrinho_id = ?
              ORDER BY ci.id ASC',
            [$carrinhoId]
        );

        $itens = [];
        foreach ($rows as $row) {
            $item = CarrinhoItem::fromRow($row);
            $imagem = $this->fetch(
                'SELECT url FROM produto_imagens WHERE produto_id = ? ORDER BY principal DESC, ordem ASC, id ASC LIMIT 1',
                [$item->produto_id]
            );
            $item->imagem = $imagem ? ['url' => (string) $imagem['url']] : null;
            $itens[] = $item;
        }
        return $itens;
    }

    public function itemPorProduto(int $carrinhoId, int $produtoId): ?CarrinhoItem
    {
        $row = $this->fetch(
            'SELECT * FROM carrinho_itens WHERE carrinho_id = ? AND produto_id = ?',
            [$carrinhoId, $produtoId]
        );
        return $row ? CarrinhoItem::fromRow($row) : null;
    }

    public function adicionarItem(int $carrinhoId, int $produtoId, int $quantidade, float $precoUnitario): int
    {
        return $this->insertGetId(
            'INSERT INTO carrinho_itens (carrinho_id, produto_id, quantidade, preco_unitario)
             VALUES (?, ?, ?, ?)',
            [$carrinhoId, $produtoId, $quantidade, $precoUnitario]
        );
    }

    public function atualizarQuantidade(int $itemId, int $quantidade): void
    {
        $this->execute(
            'UPDATE carrinho_itens SET quantidade = ? WHERE id = ?',
            [$quantidade, $itemId]
        );
    }

    public function atualizarPreco(int $itemId, float $precoUnitario): void
    {
        $this->execute(
            'UPDATE carrinho_itens SET preco_unitario = ? WHERE id = ?',
            [$precoUnitario, $itemId]
        );
    }

    public function removerItem(int $itemId): void
    {
        $this->execute('DELETE FROM carrinho_itens WHERE id = ?', [$itemId]);
    }

    public function itemPertenceAoUsuario(int $itemId, int $usuarioId): bool
    {
        $row = $this->fetch(
            'SELECT ci.id
               FROM carrinho_itens ci
               INNER JOIN carrinho c ON c.id = ci.carrinho_id
              WHERE ci.id = ? AND c.usuario_id = ?',
            [$itemId, $usuarioId]
        );
        return $row !== null;
    }

    public function limpar(int $carrinhoId): void
    {
        $this->execute('DELETE FROM carrinho_itens WHERE carrinho_id = ?', [$carrinhoId]);
        $this->touch($carrinhoId);
    }

    public function touch(int $carrinhoId): void
    {
        $this->execute('UPDATE carrinho SET updated_at = NOW() WHERE id = ?', [$carrinhoId]);
    }
}

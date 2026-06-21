<?php

declare(strict_types=1);

namespace App\Models;

final class CarrinhoItem
{
    public ?int $id = null;
    public ?int $carrinho_id = null;
    public int $produto_id = 0;
    public int $quantidade = 0;
    public float $preco_unitario = 0.0;

    public ?string $produto_nome = null;
    public ?string $produto_slug = null;
    public int $estoque_disponivel = 0;
    public bool $produto_ativo = true;
    /** @var array<string,mixed>|null */
    public ?array $imagem = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id                 = isset($row['id']) ? (int) $row['id'] : null;
        $i->carrinho_id        = isset($row['carrinho_id']) ? (int) $row['carrinho_id'] : null;
        $i->produto_id         = (int) ($row['produto_id'] ?? 0);
        $i->quantidade         = (int) ($row['quantidade'] ?? 0);
        $i->preco_unitario     = (float) ($row['preco_unitario'] ?? 0);
        $i->produto_nome       = $row['produto_nome'] ?? null;
        $i->produto_slug       = $row['produto_slug'] ?? null;
        $i->estoque_disponivel = (int) ($row['estoque_disponivel'] ?? 0);
        $i->produto_ativo      = (bool) ($row['produto_ativo'] ?? true);
        return $i;
    }

    public function subtotal(): float
    {
        return round($this->preco_unitario * $this->quantidade, 2);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'produto_id'     => $this->produto_id,
            'nome'           => $this->produto_nome,
            'slug'           => $this->produto_slug,
            'quantidade'     => $this->quantidade,
            'preco_unitario' => $this->preco_unitario,
            'subtotal'       => $this->subtotal(),
            'estoque'        => $this->estoque_disponivel,
            'imagem'         => $this->imagem,
        ];
    }
}

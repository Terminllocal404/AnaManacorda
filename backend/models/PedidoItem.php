<?php

declare(strict_types=1);

namespace App\Models;

final class PedidoItem
{
    public ?int $id = null;
    public ?int $pedido_id = null;
    public ?int $produto_id = null;
    public string $nome_produto = '';
    public int $quantidade = 0;
    public float $preco_unitario = 0.0;
    public float $subtotal = 0.0;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id             = isset($row['id']) ? (int) $row['id'] : null;
        $i->pedido_id      = isset($row['pedido_id']) ? (int) $row['pedido_id'] : null;
        $i->produto_id     = isset($row['produto_id']) ? (int) $row['produto_id'] : null;
        $i->nome_produto   = (string) ($row['nome_produto'] ?? '');
        $i->quantidade     = (int) ($row['quantidade'] ?? 0);
        $i->preco_unitario = (float) ($row['preco_unitario'] ?? 0);
        $i->subtotal       = (float) ($row['subtotal'] ?? 0);
        return $i;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'produto_id'     => $this->produto_id,
            'nome'           => $this->nome_produto,
            'quantidade'     => $this->quantidade,
            'preco_unitario' => $this->preco_unitario,
            'subtotal'       => $this->subtotal,
        ];
    }
}

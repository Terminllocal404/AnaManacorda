<?php

declare(strict_types=1);

namespace App\Models;

final class Carrinho
{
    public ?int $id = null;
    public ?int $usuario_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @var CarrinhoItem[] */
    public array $itens = [];
    public float $subtotal = 0.0;
    public float $total = 0.0;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id         = isset($row['id']) ? (int) $row['id'] : null;
        $c->usuario_id = isset($row['usuario_id']) ? (int) $row['usuario_id'] : null;
        $c->created_at = $row['created_at'] ?? null;
        $c->updated_at = $row['updated_at'] ?? null;
        return $c;
    }

    public function recalcular(): void
    {
        $subtotal = 0.0;
        foreach ($this->itens as $item) {
            $subtotal += $item->subtotal();
        }
        $this->subtotal = round($subtotal, 2);
        $this->total    = $this->subtotal;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'itens'          => array_map(static fn (CarrinhoItem $i) => $i->toArray(), $this->itens),
            'quantidade_itens' => array_sum(array_map(static fn (CarrinhoItem $i) => $i->quantidade, $this->itens)),
            'subtotal'       => $this->subtotal,
            'total'          => $this->total,
        ];
    }
}

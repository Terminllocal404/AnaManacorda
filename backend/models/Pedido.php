<?php

declare(strict_types=1);

namespace App\Models;

final class Pedido
{
    public ?int $id = null;
    public string $codigo = '';
    public ?int $usuario_id = null;
    public ?int $endereco_id = null;
    public string $status = 'AGUARDANDO_PAGAMENTO';
    public float $subtotal = 0.0;
    public float $total = 0.0;
    public ?string $observacoes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @var PedidoItem[] */
    public array $itens = [];
    public ?Endereco $endereco = null;
    /** @var array<string,mixed>|null */
    public ?array $pagamento = null;
    /** @var array<int,array<string,mixed>> */
    public array $historico = [];

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id          = isset($row['id']) ? (int) $row['id'] : null;
        $p->codigo      = (string) ($row['codigo'] ?? '');
        $p->usuario_id  = isset($row['usuario_id']) ? (int) $row['usuario_id'] : null;
        $p->endereco_id = isset($row['endereco_id']) ? (int) $row['endereco_id'] : null;
        $p->status      = (string) ($row['status'] ?? 'AGUARDANDO_PAGAMENTO');
        $p->subtotal    = (float) ($row['subtotal'] ?? 0);
        $p->total       = (float) ($row['total'] ?? 0);
        $p->observacoes = $row['observacoes'] ?? null;
        $p->created_at  = $row['created_at'] ?? null;
        $p->updated_at  = $row['updated_at'] ?? null;
        return $p;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'codigo'      => $this->codigo,
            'status'      => $this->status,
            'subtotal'    => $this->subtotal,
            'total'       => $this->total,
            'observacoes' => $this->observacoes,
            'itens'       => array_map(static fn (PedidoItem $i) => $i->toArray(), $this->itens),
            'endereco'    => $this->endereco?->toArray(),
            'pagamento'   => $this->pagamento,
            'historico'   => $this->historico,
            'created_at'  => $this->created_at,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

final class Produto
{
    public ?int $id = null;
    public ?int $categoria_id = null;
    public string $nome = '';
    public string $slug = '';
    public ?string $descricao = null;
    public float $preco = 0.0;
    public int $estoque = 0;
    public bool $ativo = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $categoria_nome = null;
    /** @var array<int,array<string,mixed>> */
    public array $imagens = [];

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id             = isset($row['id']) ? (int) $row['id'] : null;
        $p->categoria_id   = isset($row['categoria_id']) ? (int) $row['categoria_id'] : null;
        $p->nome           = (string) ($row['nome'] ?? '');
        $p->slug           = (string) ($row['slug'] ?? '');
        $p->descricao      = $row['descricao'] ?? null;
        $p->preco          = (float) ($row['preco'] ?? 0);
        $p->estoque        = (int) ($row['estoque'] ?? 0);
        $p->ativo          = (bool) ($row['ativo'] ?? true);
        $p->categoria_nome = $row['categoria_nome'] ?? null;
        $p->created_at     = $row['created_at'] ?? null;
        $p->updated_at     = $row['updated_at'] ?? null;
        return $p;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'categoria_id' => $this->categoria_id,
            'categoria'    => $this->categoria_nome,
            'nome'         => $this->nome,
            'slug'         => $this->slug,
            'descricao'    => $this->descricao,
            'preco'        => $this->preco,
            'estoque'      => $this->estoque,
            'disponivel'   => $this->ativo && $this->estoque > 0,
            'ativo'        => $this->ativo,
            'imagens'      => $this->imagens,
        ];
    }
}

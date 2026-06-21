<?php

declare(strict_types=1);

namespace App\Models;

final class Categoria
{
    public ?int $id = null;
    public string $nome = '';
    public string $slug = '';
    public ?string $descricao = null;
    public bool $ativo = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id         = isset($row['id']) ? (int) $row['id'] : null;
        $c->nome       = (string) ($row['nome'] ?? '');
        $c->slug       = (string) ($row['slug'] ?? '');
        $c->descricao  = $row['descricao'] ?? null;
        $c->ativo      = (bool) ($row['ativo'] ?? true);
        $c->created_at = $row['created_at'] ?? null;
        $c->updated_at = $row['updated_at'] ?? null;
        return $c;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'slug'      => $this->slug,
            'descricao' => $this->descricao,
            'ativo'     => $this->ativo,
        ];
    }
}

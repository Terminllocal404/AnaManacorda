<?php

declare(strict_types=1);

namespace App\Models;

final class Endereco
{
    public ?int $id = null;
    public ?int $usuario_id = null;
    public string $nome = '';
    public string $sobrenome = '';
    public string $telefone = '';
    public string $cep = '';
    public string $rua = '';
    public string $numero = '';
    public string $bairro = '';
    public ?string $complemento = null;
    public string $cidade = '';
    public string $estado = '';
    public ?string $observacoes = null;
    public ?string $created_at = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id          = isset($row['id']) ? (int) $row['id'] : null;
        $e->usuario_id  = isset($row['usuario_id']) ? (int) $row['usuario_id'] : null;
        $e->nome        = (string) ($row['nome'] ?? '');
        $e->sobrenome   = (string) ($row['sobrenome'] ?? '');
        $e->telefone    = (string) ($row['telefone'] ?? '');
        $e->cep         = (string) ($row['cep'] ?? '');
        $e->rua         = (string) ($row['rua'] ?? '');
        $e->numero      = (string) ($row['numero'] ?? '');
        $e->bairro      = (string) ($row['bairro'] ?? '');
        $e->complemento = $row['complemento'] ?? null;
        $e->cidade      = (string) ($row['cidade'] ?? '');
        $e->estado      = (string) ($row['estado'] ?? '');
        $e->observacoes = $row['observacoes'] ?? null;
        $e->created_at  = $row['created_at'] ?? null;
        return $e;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'nome'        => $this->nome,
            'sobrenome'   => $this->sobrenome,
            'telefone'    => $this->telefone,
            'cep'         => $this->cep,
            'rua'         => $this->rua,
            'numero'      => $this->numero,
            'bairro'      => $this->bairro,
            'complemento' => $this->complemento,
            'cidade'      => $this->cidade,
            'estado'      => $this->estado,
            'observacoes' => $this->observacoes,
        ];
    }
}

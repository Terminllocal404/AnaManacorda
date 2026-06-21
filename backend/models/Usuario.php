<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario
{
    public ?int $id = null;
    public string $nome = '';
    public string $sobrenome = '';
    public string $email = '';
    public ?string $telefone = null;
    public string $senha_hash = '';
    public bool $email_verificado = false;
    public string $role = 'cliente';
    public string $status = 'pendente';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id               = isset($row['id']) ? (int) $row['id'] : null;
        $u->nome             = (string) ($row['nome'] ?? '');
        $u->sobrenome        = (string) ($row['sobrenome'] ?? '');
        $u->email            = (string) ($row['email'] ?? '');
        $u->telefone         = $row['telefone'] ?? null;
        $u->senha_hash       = (string) ($row['senha_hash'] ?? '');
        $u->email_verificado = (bool) ($row['email_verificado'] ?? false);
        $u->role             = (string) ($row['role'] ?? 'cliente');
        $u->status           = (string) ($row['status'] ?? 'pendente');
        $u->created_at       = $row['created_at'] ?? null;
        $u->updated_at       = $row['updated_at'] ?? null;
        return $u;
    }

    /** @return array<string,mixed> Dados publicos (sem hash de senha). */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'nome'             => $this->nome,
            'sobrenome'        => $this->sobrenome,
            'email'            => $this->email,
            'telefone'         => $this->telefone,
            'email_verificado' => $this->email_verificado,
            'role'             => $this->role,
            'status'           => $this->status,
            'created_at'       => $this->created_at,
        ];
    }
}

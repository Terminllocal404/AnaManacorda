<?php

declare(strict_types=1);

namespace App\Models;

final class Pagamento
{
    public ?int $id = null;
    public ?int $pedido_id = null;
    public string $metodo = '';
    public string $gateway = 'mercadopago';
    public ?string $gateway_payment_id = null;
    public string $status = 'pending';
    public float $valor = 0.0;
    public ?string $qr_code = null;
    public ?string $qr_code_base64 = null;
    public ?string $ticket_url = null;
    public ?string $boleto_url = null;
    public ?string $linha_digitavel = null;
    public ?string $expira_em = null;
    /** @var array<string,mixed>|null */
    public ?array $payload = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id                 = isset($row['id']) ? (int) $row['id'] : null;
        $p->pedido_id          = isset($row['pedido_id']) ? (int) $row['pedido_id'] : null;
        $p->metodo             = (string) ($row['metodo'] ?? '');
        $p->gateway            = (string) ($row['gateway'] ?? 'mercadopago');
        $p->gateway_payment_id = $row['gateway_payment_id'] ?? null;
        $p->status             = (string) ($row['status'] ?? 'pending');
        $p->valor              = (float) ($row['valor'] ?? 0);
        $p->qr_code            = $row['qr_code'] ?? null;
        $p->qr_code_base64     = $row['qr_code_base64'] ?? null;
        $p->ticket_url         = $row['ticket_url'] ?? null;
        $p->boleto_url         = $row['boleto_url'] ?? null;
        $p->linha_digitavel    = $row['linha_digitavel'] ?? null;
        $p->expira_em          = $row['expira_em'] ?? null;
        $p->payload            = isset($row['payload']) && is_string($row['payload'])
            ? (json_decode($row['payload'], true) ?: null)
            : ($row['payload'] ?? null);
        $p->created_at         = $row['created_at'] ?? null;
        $p->updated_at         = $row['updated_at'] ?? null;
        return $p;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'metodo'             => $this->metodo,
            'status'             => $this->status,
            'valor'              => $this->valor,
            'gateway_payment_id' => $this->gateway_payment_id,
            'qr_code'            => $this->qr_code,
            'qr_code_base64'     => $this->qr_code_base64,
            'ticket_url'         => $this->ticket_url,
            'boleto_url'         => $this->boleto_url,
            'linha_digitavel'    => $this->linha_digitavel,
            'expira_em'          => $this->expira_em,
        ];
    }
}

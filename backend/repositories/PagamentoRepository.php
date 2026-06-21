<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Pagamento;

final class PagamentoRepository extends BaseRepository
{
    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO pagamentos
                (pedido_id, metodo, gateway, gateway_payment_id, status, valor,
                 qr_code, qr_code_base64, ticket_url, boleto_url, linha_digitavel, expira_em, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['pedido_id'],
                $dados['metodo'],
                $dados['gateway'] ?? 'mercadopago',
                $dados['gateway_payment_id'] ?? null,
                $dados['status'] ?? 'pending',
                $dados['valor'],
                $dados['qr_code'] ?? null,
                $dados['qr_code_base64'] ?? null,
                $dados['ticket_url'] ?? null,
                $dados['boleto_url'] ?? null,
                $dados['linha_digitavel'] ?? null,
                $dados['expira_em'] ?? null,
                isset($dados['payload']) ? json_encode($dados['payload'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }

    public function buscarPorId(int $id): ?Pagamento
    {
        $row = $this->fetch('SELECT * FROM pagamentos WHERE id = ?', [$id]);
        return $row ? Pagamento::fromRow($row) : null;
    }

    public function buscarPorPedido(int $pedidoId): ?Pagamento
    {
        $row = $this->fetch(
            'SELECT * FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1',
            [$pedidoId]
        );
        return $row ? Pagamento::fromRow($row) : null;
    }

    public function buscarPorGatewayId(string $gatewayPaymentId): ?Pagamento
    {
        $row = $this->fetch(
            'SELECT * FROM pagamentos WHERE gateway_payment_id = ? ORDER BY id DESC LIMIT 1',
            [$gatewayPaymentId]
        );
        return $row ? Pagamento::fromRow($row) : null;
    }

    public function atualizarStatus(int $id, string $status, ?array $payload = null): void
    {
        if ($payload !== null) {
            $this->execute(
                'UPDATE pagamentos SET status = ?, payload = ?, updated_at = NOW() WHERE id = ?',
                [$status, json_encode($payload, JSON_UNESCAPED_UNICODE), $id]
            );
            return;
        }

        $this->execute(
            'UPDATE pagamentos SET status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $id]
        );
    }

    public function vincularGatewayId(int $id, string $gatewayPaymentId): void
    {
        $this->execute(
            'UPDATE pagamentos SET gateway_payment_id = ?, updated_at = NOW() WHERE id = ?',
            [$gatewayPaymentId, $id]
        );
    }
}

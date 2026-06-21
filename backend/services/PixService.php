<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Logica de dominio especifica do PIX.
 *
 * Monta o payload de dominio a partir de um pedido e delega a criacao
 * efetiva ao gateway (MercadoPagoService), retornando os dados que serao
 * persistidos no pagamento e exibidos ao cliente (QR Code e copia-e-cola).
 */
final class PixService
{
    public function __construct(
        private MercadoPagoService $mercadoPago = new MercadoPagoService()
    ) {
    }

    /**
     * Gera um pagamento PIX para um pedido.
     *
     * @param array{codigo:string,total:float} $pedido
     * @param array{email:string,nome?:string,sobrenome?:string,cpf?:string} $pagador
     * @return array<string,mixed>
     */
    public function gerar(array $pedido, array $pagador): array
    {
        $resultado = $this->mercadoPago->criarPagamentoPix([
            'valor'      => (float) $pedido['total'],
            'email'      => $pagador['email'],
            'nome'       => $pagador['nome'] ?? null,
            'sobrenome'  => $pagador['sobrenome'] ?? null,
            'cpf'        => isset($pagador['cpf']) ? preg_replace('/\D/', '', $pagador['cpf']) : null,
            'descricao'  => 'Pedido ' . $pedido['codigo'] . ' - Ana Manacorda',
            'referencia' => $pedido['codigo'],
        ]);

        return [
            'gateway_payment_id' => $resultado['id'],
            'status'             => $resultado['status'],
            'valor'              => $resultado['valor'],
            'qr_code'            => $resultado['qr_code'],
            'qr_code_base64'     => $resultado['qr_code_base64'],
            'ticket_url'         => $resultado['ticket_url'],
        ];
    }
}

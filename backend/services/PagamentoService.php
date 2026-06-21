<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Validator;
use App\Repositories\PagamentoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\UsuarioRepository;

/**
 * Orquestra pagamentos via Mercado Pago e o processamento de webhooks.
 *
 * Metodos suportados: PIX, cartao de credito, cartao de debito e boleto.
 * A confirmacao de pagamento atualiza o pedido de forma idempotente.
 */
final class PagamentoService
{
    /** Status do gateway que indicam pagamento aprovado. */
    private const STATUS_APROVADO = ['approved'];

    public function __construct(
        private PedidoRepository $pedidos = new PedidoRepository(),
        private PagamentoRepository $pagamentos = new PagamentoRepository(),
        private UsuarioRepository $usuarios = new UsuarioRepository(),
        private MercadoPagoService $mercadoPago = new MercadoPagoService(),
        private PixService $pix = new PixService(),
        private PedidoService $pedidoService = new PedidoService(),
        private LogService $log = new LogService()
    ) {
    }

    /**
     * Inicia um pagamento PIX para um pedido do usuario.
     *
     * @return array<string,mixed>
     */
    public function iniciarPix(int $usuarioId, int $pedidoId, ?string $cpf, string $ip): array
    {
        $pedido  = $this->pedidoEditavel($pedidoId, $usuarioId);
        $usuario = $this->usuarios->buscarPorId($usuarioId);

        // Reaproveita um PIX pendente ainda valido, se existir.
        $existente = $this->pagamentos->buscarPorPedido($pedidoId);
        if ($existente !== null && $existente->metodo === 'pix' && $existente->status === 'pending') {
            return $existente->toArray();
        }

        $dados = $this->pix->gerar(
            ['codigo' => $pedido->codigo, 'total' => $pedido->total],
            [
                'email'     => $usuario?->email ?? ($pedido->endereco?->nome ?? '') . '@sememail.local',
                'nome'      => $pedido->endereco?->nome,
                'sobrenome' => $pedido->endereco?->sobrenome,
                'cpf'       => $cpf,
            ]
        );

        $pagamentoId = $this->pagamentos->criar([
            'pedido_id'          => $pedidoId,
            'metodo'             => 'pix',
            'gateway'            => 'mercadopago',
            'gateway_payment_id' => $dados['gateway_payment_id'],
            'status'             => $dados['status'],
            'valor'             => $pedido->total,
            'qr_code'            => $dados['qr_code'],
            'qr_code_base64'     => $dados['qr_code_base64'],
            'ticket_url'         => $dados['ticket_url'],
            'expira_em'          => null,
            'payload'            => $dados,
        ]);

        $this->log->registrar('pagamento_pix', $usuarioId, $ip, 'info', 'PIX gerado para pedido ' . $pedido->codigo, [
            'pagamento_id' => $pagamentoId,
        ]);

        $pagamento = $this->pagamentos->buscarPorId($pagamentoId);
        return $pagamento?->toArray() ?? $dados;
    }

    /**
     * Processa um pagamento com cartao (credito ou debito) usando o token do frontend.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function processarCartao(int $usuarioId, int $pedidoId, array $dados, string $ip): array
    {
        Validator::make($dados, [
            'token'             => 'required|string',
            'payment_method_id' => 'required|string',
            'parcelas'          => 'integer|min:1|max:24',
            'tipo'              => 'in:credito,debito',
        ], [
            'token'             => 'token do cartao',
            'payment_method_id' => 'metodo de pagamento',
            'parcelas'          => 'parcelas',
        ])->validate();

        $pedido  = $this->pedidoEditavel($pedidoId, $usuarioId);
        $usuario = $this->usuarios->buscarPorId($usuarioId);
        $tipo    = (string) ($dados['tipo'] ?? 'credito');
        $metodo  = $tipo === 'debito' ? 'cartao_debito' : 'cartao_credito';

        $resultado = $this->mercadoPago->criarPagamentoCartao([
            'token'             => (string) $dados['token'],
            'valor'             => $pedido->total,
            'email'             => $usuario?->email ?? '',
            'descricao'         => 'Pedido ' . $pedido->codigo . ' - Ana Manacorda',
            'referencia'        => $pedido->codigo,
            'parcelas'          => (int) ($dados['parcelas'] ?? 1),
            'payment_method_id' => (string) $dados['payment_method_id'],
            'issuer_id'         => isset($dados['issuer_id']) ? (string) $dados['issuer_id'] : null,
            'cpf'               => isset($dados['cpf']) ? (preg_replace('/\D/', '', (string) $dados['cpf']) ?? '') : null,
        ]);

        $pagamentoId = $this->pagamentos->criar([
            'pedido_id'          => $pedidoId,
            'metodo'             => $metodo,
            'gateway'            => 'mercadopago',
            'gateway_payment_id' => $resultado['id'],
            'status'             => $resultado['status'],
            'valor'             => $pedido->total,
            'payload'            => $resultado,
        ]);

        // Cartao costuma retornar status final de forma sincrona.
        if (in_array($resultado['status'], self::STATUS_APROVADO, true)) {
            $this->pedidoService->marcarComoPago($pedidoId, $usuarioId, $ip);
        }

        $this->log->registrar('pagamento_cartao', $usuarioId, $ip, 'info', 'Pagamento cartao para pedido ' . $pedido->codigo, [
            'pagamento_id' => $pagamentoId,
            'status'       => $resultado['status'],
        ]);

        $pagamento = $this->pagamentos->buscarPorId($pagamentoId);
        return $pagamento?->toArray() ?? $resultado;
    }

    /**
     * Gera um boleto bancario para o pedido.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function gerarBoleto(int $usuarioId, int $pedidoId, array $dados, string $ip): array
    {
        Validator::make($dados, [
            'cpf' => 'required|cpf',
        ], ['cpf' => 'CPF'])->validate();

        $pedido  = $this->pedidoEditavel($pedidoId, $usuarioId);
        $usuario = $this->usuarios->buscarPorId($usuarioId);
        $end     = $pedido->endereco;

        if ($end === null) {
            throw new HttpException('Endereco do pedido nao encontrado para emissao do boleto.', 422);
        }

        $resultado = $this->mercadoPago->criarPagamentoBoleto([
            'valor'      => $pedido->total,
            'email'      => $usuario?->email ?? '',
            'descricao'  => 'Pedido ' . $pedido->codigo . ' - Ana Manacorda',
            'referencia' => $pedido->codigo,
            'nome'       => $end->nome,
            'sobrenome'  => $end->sobrenome,
            'cpf'        => preg_replace('/\D/', '', (string) $dados['cpf']) ?? '',
            'cep'        => $end->cep,
            'rua'        => $end->rua,
            'numero'     => $end->numero,
            'bairro'     => $end->bairro,
            'cidade'     => $end->cidade,
            'estado'     => $end->estado,
        ]);

        $pagamentoId = $this->pagamentos->criar([
            'pedido_id'          => $pedidoId,
            'metodo'             => 'boleto',
            'gateway'            => 'mercadopago',
            'gateway_payment_id' => $resultado['id'],
            'status'             => $resultado['status'],
            'valor'             => $pedido->total,
            'boleto_url'         => $resultado['boleto_url'],
            'ticket_url'         => $resultado['ticket_url'],
            'linha_digitavel'    => $resultado['linha_digitavel'],
            'payload'            => $resultado,
        ]);

        $this->log->registrar('pagamento_boleto', $usuarioId, $ip, 'info', 'Boleto gerado para pedido ' . $pedido->codigo, [
            'pagamento_id' => $pagamentoId,
        ]);

        $pagamento = $this->pagamentos->buscarPorId($pagamentoId);
        return $pagamento?->toArray() ?? $resultado;
    }

    /**
     * Consulta o status atual de um pagamento de um pedido do usuario.
     *
     * @return array<string,mixed>
     */
    public function statusDoPedido(int $usuarioId, int $pedidoId): array
    {
        $pedido = $this->pedidos->buscarPorIdEUsuario($pedidoId, $usuarioId);
        if ($pedido === null) {
            throw new HttpException('Pedido nao encontrado.', 404);
        }

        $pagamento = $this->pagamentos->buscarPorPedido($pedidoId);
        return [
            'pedido_status'    => $pedido->status,
            'pagamento'        => $pagamento?->toArray(),
        ];
    }

    /**
     * Processa uma notificacao de webhook do Mercado Pago.
     *
     * @return array<string,mixed>
     */
    public function processarWebhook(string $tipo, string $dataId, string $ip): array
    {
        if ($tipo !== 'payment' || $dataId === '') {
            // Outros tipos de evento sao ignorados com sucesso.
            return ['processado' => false, 'motivo' => 'evento ignorado'];
        }

        // Consulta o pagamento real no gateway (fonte da verdade).
        $info = $this->mercadoPago->obterPagamento($dataId);

        $pagamento = $this->pagamentos->buscarPorGatewayId($dataId);
        if ($pagamento === null || $pagamento->id === null) {
            $this->log->registrar('webhook_sem_pagamento', null, $ip, 'warning', 'Webhook para pagamento desconhecido: ' . $dataId);
            return ['processado' => false, 'motivo' => 'pagamento nao encontrado'];
        }

        $statusGateway = (string) $info['status'];
        $this->pagamentos->atualizarStatus($pagamento->id, $statusGateway, $info);

        if (in_array($statusGateway, self::STATUS_APROVADO, true) && $pagamento->pedido_id !== null) {
            $pedido = $this->pedidos->buscarPorId($pagamento->pedido_id, false);
            $this->pedidoService->marcarComoPago($pagamento->pedido_id, $pedido?->usuario_id, $ip);
        }

        $this->log->registrar('webhook_processado', null, $ip, 'info', 'Webhook MP processado: ' . $dataId, [
            'status' => $statusGateway,
        ]);

        return ['processado' => true, 'status' => $statusGateway];
    }

    private function pedidoEditavel(int $pedidoId, int $usuarioId): \App\Models\Pedido
    {
        $pedido = $this->pedidos->buscarPorIdEUsuario($pedidoId, $usuarioId);
        if ($pedido === null) {
            throw new HttpException('Pedido nao encontrado.', 404);
        }
        if ($pedido->status !== 'AGUARDANDO_PAGAMENTO') {
            throw new HttpException('Este pedido nao esta aguardando pagamento.', 422);
        }
        return $pedido;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Models\Pedido;
use App\Repositories\CarrinhoRepository;
use App\Repositories\EnderecoRepository;
use App\Repositories\PedidoRepository;
use App\Repositories\ProdutoRepository;
use App\Repositories\UsuarioRepository;
use Throwable;

/**
 * Orquestra o ciclo de vida dos pedidos: criacao transacional a partir do
 * carrinho e transicoes de status validadas.
 */
final class PedidoService
{
    public const STATUS = [
        'AGUARDANDO_PAGAMENTO',
        'PAGO',
        'SEPARANDO',
        'PRONTO_PARA_ENTREGA',
        'ENTREGUE',
        'FINALIZADO',
    ];

    /** Transicoes permitidas: status atual => proximos status validos. */
    private const TRANSICOES = [
        'AGUARDANDO_PAGAMENTO' => ['PAGO'],
        'PAGO'                 => ['SEPARANDO'],
        'SEPARANDO'            => ['PRONTO_PARA_ENTREGA'],
        'PRONTO_PARA_ENTREGA'  => ['ENTREGUE'],
        'ENTREGUE'             => ['FINALIZADO'],
        'FINALIZADO'           => [],
    ];

    public function __construct(
        private PedidoRepository $pedidos = new PedidoRepository(),
        private EnderecoRepository $enderecos = new EnderecoRepository(),
        private ProdutoRepository $produtos = new ProdutoRepository(),
        private CarrinhoRepository $carrinhos = new CarrinhoRepository(),
        private UsuarioRepository $usuarios = new UsuarioRepository(),
        private CodigoService $codigos = new CodigoService(),
        private EmailService $email = new EmailService(),
        private LogService $log = new LogService()
    ) {
    }

    /**
     * Cria um pedido a partir do carrinho do usuario, em uma unica transacao.
     *
     * @param array<string,mixed> $endereco dados ja validados pelo CheckoutService
     */
    public function criarPedido(int $usuarioId, array $endereco, string $ip): Pedido
    {
        $carrinho = $this->carrinhos->buscarPorUsuario($usuarioId);
        if ($carrinho === null || $carrinho->id === null || $carrinho->itens === []) {
            throw new HttpException('Seu carrinho esta vazio.', 422);
        }

        Database::beginTransaction();
        try {
            // 1) Revalida estoque e precos com lock; monta os itens do pedido.
            $itensPedido = [];
            $subtotal    = 0.0;

            foreach ($carrinho->itens as $item) {
                $produto = $this->produtos->lockPorId($item->produto_id);
                if ($produto === null || !$produto->ativo) {
                    throw new ValidationException([
                        'itens' => ['O produto "' . ($item->produto_nome ?? '#' . $item->produto_id) . '" nao esta mais disponivel.'],
                    ]);
                }
                if ($produto->estoque < $item->quantidade) {
                    throw new ValidationException([
                        'itens' => ['Estoque insuficiente para "' . $produto->nome . '". Disponivel: ' . $produto->estoque . '.'],
                    ]);
                }

                $precoUnitario = $produto->preco; // preco autoritativo do banco
                $subtotalItem  = round($precoUnitario * $item->quantidade, 2);
                $subtotal     += $subtotalItem;

                $itensPedido[] = [
                    'produto_id'     => (int) $produto->id,
                    'nome'           => $produto->nome,
                    'quantidade'     => $item->quantidade,
                    'preco_unitario' => $precoUnitario,
                ];
            }

            $subtotal = round($subtotal, 2);
            $total    = $subtotal; // sem frete/desconto nesta etapa

            // 2) Persiste o endereco do pedido.
            $enderecoId = $this->enderecos->criar([
                'usuario_id'  => $usuarioId,
                'nome'        => $endereco['nome'],
                'sobrenome'   => $endereco['sobrenome'],
                'telefone'    => $endereco['telefone'],
                'cep'         => $endereco['cep'],
                'rua'         => $endereco['rua'],
                'numero'      => $endereco['numero'],
                'bairro'      => $endereco['bairro'],
                'complemento' => $endereco['complemento'] ?? null,
                'cidade'      => $endereco['cidade'],
                'estado'      => $endereco['estado'],
                'observacoes' => $endereco['observacoes'] ?? null,
            ]);

            // 3) Gera o codigo sequencial (com lock dentro desta transacao).
            $codigo = $this->codigos->gerarCodigoPedido();

            // 4) Cria o pedido e seus itens (snapshot dos precos).
            $pedidoId = $this->pedidos->criar([
                'codigo'      => $codigo,
                'usuario_id'  => $usuarioId,
                'endereco_id' => $enderecoId,
                'status'      => 'AGUARDANDO_PAGAMENTO',
                'subtotal'    => $subtotal,
                'total'       => $total,
                'observacoes' => $endereco['observacoes'] ?? null,
            ]);

            foreach ($itensPedido as $it) {
                $this->pedidos->adicionarItem(
                    $pedidoId,
                    $it['produto_id'],
                    $it['nome'],
                    $it['quantidade'],
                    $it['preco_unitario']
                );

                // 5) Baixa o estoque de forma segura.
                if (!$this->produtos->baixarEstoque($it['produto_id'], $it['quantidade'])) {
                    throw new ValidationException([
                        'itens' => ['Estoque insuficiente para "' . $it['nome'] . '".'],
                    ]);
                }
            }

            // 6) Registra historico inicial e limpa o carrinho.
            $this->pedidos->registrarHistorico($pedidoId, null, 'AGUARDANDO_PAGAMENTO', $usuarioId, 'Pedido criado.');
            $this->carrinhos->limpar($carrinho->id);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        $pedido = $this->pedidos->buscarPorId($pedidoId);
        /** @var Pedido $pedido */

        // 7) Pos-commit: e-mail de confirmacao e log (falhas aqui nao revertem o pedido).
        $usuario = $this->usuarios->buscarPorId($usuarioId);
        if ($usuario !== null) {
            try {
                $this->email->enviarConfirmacaoPedido($usuario->email, $usuario->nome, $pedido->toArray());
            } catch (Throwable $e) {
                $this->log->erro('email_confirmacao_falha', $usuarioId, $ip, $e->getMessage(), ['pedido' => $codigo]);
            }
        }

        $this->log->registrar('pedido_criado', $usuarioId, $ip, 'info', 'Pedido ' . $codigo . ' criado.', [
            'pedido_id' => $pedidoId,
            'total'     => $total,
        ]);

        return $pedido;
    }

    /**
     * Marca o pedido como PAGO de forma idempotente (chamado pelo fluxo de pagamento).
     * Se ja estiver pago ou em status posterior, nao faz nada.
     */
    public function marcarComoPago(int $pedidoId, ?int $usuarioId, string $ip): void
    {
        Database::beginTransaction();
        try {
            $pedido = $this->pedidos->lockPorId($pedidoId);
            if ($pedido === null) {
                Database::rollBack();
                throw new HttpException('Pedido nao encontrado.', 404);
            }

            // Idempotencia: so transiciona se ainda estiver aguardando pagamento.
            if ($pedido->status !== 'AGUARDANDO_PAGAMENTO') {
                Database::commit();
                return;
            }

            $this->pedidos->atualizarStatus($pedidoId, 'PAGO');
            $this->pedidos->registrarHistorico($pedidoId, 'AGUARDANDO_PAGAMENTO', 'PAGO', $usuarioId, 'Pagamento confirmado.');

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        $this->log->registrar('pedido_pago', $usuarioId, $ip, 'info', 'Pedido #' . $pedidoId . ' marcado como PAGO.');
    }

    /**
     * Atualiza o status de um pedido respeitando a maquina de estados.
     */
    public function atualizarStatus(int $pedidoId, string $novoStatus, ?int $usuarioId, string $ip, ?string $observacao = null): Pedido
    {
        $novoStatus = strtoupper(trim($novoStatus));
        if (!in_array($novoStatus, self::STATUS, true)) {
            throw new ValidationException(['status' => ['Status invalido.']]);
        }

        Database::beginTransaction();
        try {
            $pedido = $this->pedidos->lockPorId($pedidoId);
            if ($pedido === null) {
                Database::rollBack();
                throw new HttpException('Pedido nao encontrado.', 404);
            }

            if ($pedido->status === $novoStatus) {
                Database::commit();
                return $this->pedidos->buscarPorId($pedidoId) ?? $pedido;
            }

            $permitidos = self::TRANSICOES[$pedido->status] ?? [];
            if (!in_array($novoStatus, $permitidos, true)) {
                Database::rollBack();
                throw new HttpException(
                    'Transicao de status invalida: ' . $pedido->status . ' -> ' . $novoStatus . '.',
                    422
                );
            }

            $this->pedidos->atualizarStatus($pedidoId, $novoStatus);
            $this->pedidos->registrarHistorico($pedidoId, $pedido->status, $novoStatus, $usuarioId, $observacao);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        $this->log->registrar('pedido_status', $usuarioId, $ip, 'info', 'Pedido #' . $pedidoId . ' -> ' . $novoStatus . '.');

        /** @var Pedido $atualizado */
        $atualizado = $this->pedidos->buscarPorId($pedidoId);
        return $atualizado;
    }

    /** @return array<int,array<string,mixed>> */
    public function listarPorUsuario(int $usuarioId): array
    {
        return array_map(
            static fn (Pedido $p) => $p->toArray(),
            $this->pedidos->listarPorUsuario($usuarioId)
        );
    }

    /**
     * Historico de compras do cliente (pedidos efetivados, do mais recente ao
     * mais antigo).
     *
     * @return array<int,array<string,mixed>>
     */
    public function historicoDeCompras(int $usuarioId): array
    {
        return array_map(
            static fn (Pedido $p) => $p->toArray(),
            $this->pedidos->historicoPorUsuario($usuarioId)
        );
    }

    /** @return array<string,mixed> */
    public function obterDoUsuario(int $pedidoId, int $usuarioId): array
    {
        $pedido = $this->pedidos->buscarPorIdEUsuario($pedidoId, $usuarioId);
        if ($pedido === null) {
            throw new HttpException('Pedido nao encontrado.', 404);
        }
        return $pedido->toArray();
    }

    /** @return array<string,mixed> */
    public function obterPorCodigoDoUsuario(string $codigo, int $usuarioId): array
    {
        $pedido = $this->pedidos->buscarPorCodigo($codigo);
        if ($pedido === null || $pedido->usuario_id !== $usuarioId) {
            throw new HttpException('Pedido nao encontrado.', 404);
        }
        return $pedido->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Endereco;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\PedidoItem;

final class PedidoRepository extends BaseRepository
{
    /** @param array<string,mixed> $dados */
    public function criar(array $dados): int
    {
        return $this->insertGetId(
            'INSERT INTO pedidos (codigo, usuario_id, endereco_id, status, subtotal, total, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['codigo'],
                $dados['usuario_id'],
                $dados['endereco_id'],
                $dados['status'] ?? 'AGUARDANDO_PAGAMENTO',
                $dados['subtotal'],
                $dados['total'],
                $dados['observacoes'] ?? null,
            ]
        );
    }

    public function adicionarItem(int $pedidoId, int $produtoId, string $nomeProduto, int $quantidade, float $precoUnitario): int
    {
        $subtotal = round($precoUnitario * $quantidade, 2);
        return $this->insertGetId(
            'INSERT INTO pedido_itens (pedido_id, produto_id, nome_produto, quantidade, preco_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$pedidoId, $produtoId, $nomeProduto, $quantidade, $precoUnitario, $subtotal]
        );
    }

    public function buscarPorId(int $id, bool $completo = true): ?Pedido
    {
        $row = $this->fetch('SELECT * FROM pedidos WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        return $completo ? $this->montarCompleto($row) : Pedido::fromRow($row);
    }

    public function buscarPorIdEUsuario(int $id, int $usuarioId): ?Pedido
    {
        $row = $this->fetch('SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?', [$id, $usuarioId]);
        if ($row === null) {
            return null;
        }
        return $this->montarCompleto($row);
    }

    public function buscarPorCodigo(string $codigo): ?Pedido
    {
        $row = $this->fetch('SELECT * FROM pedidos WHERE codigo = ?', [$codigo]);
        if ($row === null) {
            return null;
        }
        return $this->montarCompleto($row);
    }

    /**
     * Lista pedidos de um usuario (sem itens, para a listagem da area do cliente).
     *
     * @return Pedido[]
     */
    public function listarPorUsuario(int $usuarioId): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY id DESC',
            [$usuarioId]
        );

        $pedidos = [];
        foreach ($rows as $row) {
            $pedido = Pedido::fromRow($row);
            if ($pedido->id !== null) {
                $pedido->itens     = $this->itens($pedido->id);
                $pedido->pagamento = $this->pagamentoResumo($pedido->id);
            }
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    /** @return PedidoItem[] */
    public function itens(int $pedidoId): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM pedido_itens WHERE pedido_id = ? ORDER BY id ASC',
            [$pedidoId]
        );
        return array_map(static fn (array $row) => PedidoItem::fromRow($row), $rows);
    }

    /**
     * Historico de compras: pedidos do usuario que avancaram alem do status
     * inicial de aguardando pagamento (ou seja, compras efetivadas), do mais
     * recente para o mais antigo.
     *
     * @return Pedido[]
     */
    public function historicoPorUsuario(int $usuarioId): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM pedidos
             WHERE usuario_id = ? AND status <> 'AGUARDANDO_PAGAMENTO'
             ORDER BY id DESC",
            [$usuarioId]
        );

        $pedidos = [];
        foreach ($rows as $row) {
            $pedido = Pedido::fromRow($row);
            if ($pedido->id !== null) {
                $pedido->itens     = $this->itens($pedido->id);
                $pedido->pagamento = $this->pagamentoResumo($pedido->id);
            }
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    public function atualizarStatus(int $pedidoId, string $status): void
    {
        $this->execute(
            'UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $pedidoId]
        );
    }

    public function registrarHistorico(int $pedidoId, ?string $de, string $para, ?int $usuarioId, ?string $observacao = null): void
    {
        $this->execute(
            'INSERT INTO historico_status (pedido_id, status_anterior, status_novo, usuario_id, observacao)
             VALUES (?, ?, ?, ?, ?)',
            [$pedidoId, $de, $para, $usuarioId, $observacao]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function historico(int $pedidoId): array
    {
        $rows = $this->fetchAll(
            'SELECT status_anterior, status_novo, observacao, created_at
               FROM historico_status
              WHERE pedido_id = ?
              ORDER BY id ASC',
            [$pedidoId]
        );

        return array_map(static function (array $row): array {
            return [
                'status_anterior' => $row['status_anterior'],
                'status_novo'     => (string) $row['status_novo'],
                'observacao'      => $row['observacao'],
                'data'            => $row['created_at'],
            ];
        }, $rows);
    }

    /** Bloqueia o pedido para atualizacao consistente de status/pagamento. */
    public function lockPorId(int $id): ?Pedido
    {
        $row = $this->fetch('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$id]);
        return $row ? Pedido::fromRow($row) : null;
    }

    /**
     * Retorna o ultimo codigo de pedido do ano informado, com lock, para geracao sequencial.
     */
    public function ultimoCodigoComLock(string $prefixoAno): ?string
    {
        $row = $this->fetch(
            "SELECT codigo FROM pedidos WHERE codigo LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [$prefixoAno . '%']
        );
        return $row !== null ? (string) $row['codigo'] : null;
    }

    /** @param array<string,mixed> $row */
    private function montarCompleto(array $row): Pedido
    {
        $pedido = Pedido::fromRow($row);
        if ($pedido->id === null) {
            return $pedido;
        }

        $pedido->itens     = $this->itens($pedido->id);
        $pedido->historico = $this->historico($pedido->id);
        $pedido->pagamento = $this->pagamentoResumo($pedido->id);

        if ($pedido->endereco_id !== null) {
            $end = $this->fetch('SELECT * FROM enderecos WHERE id = ?', [$pedido->endereco_id]);
            if ($end !== null) {
                $pedido->endereco = Endereco::fromRow($end);
            }
        }

        return $pedido;
    }

    /** @return array<string,mixed>|null */
    private function pagamentoResumo(int $pedidoId): ?array
    {
        $row = $this->fetch(
            'SELECT * FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1',
            [$pedidoId]
        );
        return $row !== null ? Pagamento::fromRow($row)->toArray() : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Services\PedidoService;

final class PedidoController extends Controller
{
    public function __construct(
        private PedidoService $pedidos = new PedidoService()
    ) {
    }

    public function listar(Request $request): void
    {
        $dados = $this->pedidos->listarPorUsuario((int) Auth::id());
        $this->success('Pedidos listados.', ['pedidos' => $dados]);
    }

    public function obter(Request $request): void
    {
        $pedidoId = (int) $request->param('id');
        $dados    = $this->pedidos->obterDoUsuario($pedidoId, (int) Auth::id());
        $this->success('Detalhes do pedido.', ['pedido' => $dados]);
    }

    public function obterPorCodigo(Request $request): void
    {
        $codigo = (string) $request->param('codigo');
        $dados  = $this->pedidos->obterPorCodigoDoUsuario($codigo, (int) Auth::id());
        $this->success('Detalhes do pedido.', ['pedido' => $dados]);
    }

    /** Atualizacao de status (rota protegida por AdminMiddleware). */
    public function atualizarStatus(Request $request): void
    {
        $pedidoId   = (int) $request->param('id');
        $novoStatus = (string) ($request->input('status') ?? '');
        $observacao = $request->input('observacao');
        $observacao = is_string($observacao) ? $observacao : null;

        $pedido = $this->pedidos->atualizarStatus(
            $pedidoId,
            $novoStatus,
            Auth::id(),
            $request->ip(),
            $observacao
        );

        $this->success('Status do pedido atualizado.', ['pedido' => $pedido->toArray()]);
    }
}

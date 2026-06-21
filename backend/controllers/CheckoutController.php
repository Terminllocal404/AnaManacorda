<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Services\CheckoutService;
use App\Services\PedidoService;

final class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout = new CheckoutService(),
        private PedidoService $pedidos = new PedidoService()
    ) {
    }

    /**
     * Valida apenas a area de entrega (usado para feedback imediato no frontend,
     * antes de finalizar o pedido).
     */
    public function validarEntrega(Request $request): void
    {
        $cidade = (string) ($request->input('cidade') ?? '');
        $estado = (string) ($request->input('estado') ?? '');
        $this->checkout->validarEntrega($cidade, $estado);
        $this->success('Entrega disponivel para esta localidade.');
    }

    /**
     * Finaliza o checkout: valida os dados de entrega e cria o pedido a partir
     * do carrinho do usuario.
     */
    public function finalizar(Request $request): void
    {
        $endereco = $this->checkout->validarDadosEntrega($request->all());
        $pedido   = $this->pedidos->criarPedido((int) Auth::id(), $endereco, $request->ip());

        $this->success('Pedido criado com sucesso.', ['pedido' => $pedido->toArray()], 201);
    }
}

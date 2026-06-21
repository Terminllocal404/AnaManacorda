<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Services\CarrinhoService;

final class CarrinhoController extends Controller
{
    public function __construct(
        private CarrinhoService $carrinho = new CarrinhoService()
    ) {
    }

    public function obter(Request $request): void
    {
        $dados = $this->carrinho->obter((int) Auth::id());
        $this->success('Carrinho carregado.', ['carrinho' => $dados]);
    }

    public function adicionar(Request $request): void
    {
        $dados = $this->carrinho->adicionar((int) Auth::id(), $request->all());
        $this->success('Produto adicionado ao carrinho.', ['carrinho' => $dados]);
    }

    public function atualizar(Request $request): void
    {
        $itemId     = (int) $request->param('id');
        $quantidade = (int) ($request->input('quantidade') ?? 0);
        $dados      = $this->carrinho->atualizarQuantidade((int) Auth::id(), $itemId, $quantidade);
        $this->success('Carrinho atualizado.', ['carrinho' => $dados]);
    }

    public function remover(Request $request): void
    {
        $itemId = (int) $request->param('id');
        $dados  = $this->carrinho->remover((int) Auth::id(), $itemId);
        $this->success('Item removido do carrinho.', ['carrinho' => $dados]);
    }

    public function limpar(Request $request): void
    {
        $dados = $this->carrinho->limpar((int) Auth::id());
        $this->success('Carrinho esvaziado.', ['carrinho' => $dados]);
    }
}

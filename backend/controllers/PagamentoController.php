<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Services\PagamentoService;

final class PagamentoController extends Controller
{
    public function __construct(
        private PagamentoService $pagamentos = new PagamentoService()
    ) {
    }

    public function pix(Request $request): void
    {
        $pedidoId = (int) ($request->input('pedido_id') ?? 0);
        $cpf      = $request->input('cpf');
        $cpf      = is_string($cpf) ? $cpf : null;

        $dados = $this->pagamentos->iniciarPix((int) Auth::id(), $pedidoId, $cpf, $request->ip());
        $this->success('Pagamento PIX gerado.', ['pagamento' => $dados]);
    }

    public function cartao(Request $request): void
    {
        $pedidoId = (int) ($request->input('pedido_id') ?? 0);
        $dados    = $this->pagamentos->processarCartao((int) Auth::id(), $pedidoId, $request->all(), $request->ip());
        $this->success('Pagamento processado.', ['pagamento' => $dados]);
    }

    public function boleto(Request $request): void
    {
        $pedidoId = (int) ($request->input('pedido_id') ?? 0);
        $dados    = $this->pagamentos->gerarBoleto((int) Auth::id(), $pedidoId, $request->all(), $request->ip());
        $this->success('Boleto gerado.', ['pagamento' => $dados]);
    }

    public function status(Request $request): void
    {
        $pedidoId = (int) $request->param('pedido_id');
        $dados    = $this->pagamentos->statusDoPedido((int) Auth::id(), $pedidoId);
        $this->success('Status do pagamento.', $dados);
    }
}

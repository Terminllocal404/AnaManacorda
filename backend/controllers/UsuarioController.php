<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Services\PedidoService;
use App\Services\UsuarioService;

final class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioService $usuarios = new UsuarioService(),
        private PedidoService $pedidos = new PedidoService()
    ) {
    }

    public function perfil(Request $request): void
    {
        $dados = $this->usuarios->perfil((int) Auth::id());
        $this->success('Dados do cliente.', ['usuario' => $dados]);
    }

    public function atualizarPerfil(Request $request): void
    {
        $dados = $this->usuarios->atualizarPerfil((int) Auth::id(), $request->all(), $request->ip());
        $this->success('Dados atualizados com sucesso.', ['usuario' => $dados]);
    }

    public function enderecos(Request $request): void
    {
        $dados = $this->usuarios->enderecos((int) Auth::id());
        $this->success('Enderecos listados.', ['enderecos' => $dados]);
    }

    public function historico(Request $request): void
    {
        $dados = $this->pedidos->historicoDeCompras((int) Auth::id());
        $this->success('Historico de compras.', ['pedidos' => $dados]);
    }
}

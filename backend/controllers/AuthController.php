<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Services\AuthService;
use App\Services\UsuarioService;

final class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth = new AuthService(),
        private UsuarioService $usuarios = new UsuarioService()
    ) {
    }

    public function registrar(Request $request): void
    {
        $resultado = $this->auth->registrar($request->all(), $request->ip());
        $this->success(
            'Cadastro realizado. Enviamos um e-mail para confirmacao da sua conta.',
            $resultado,
            201
        );
    }

    public function verificarEmail(Request $request): void
    {
        $token = (string) ($request->input('token') ?? '');
        $this->auth->verificarEmail($token, $request->ip());
        $this->success('E-mail confirmado com sucesso. Voce ja pode acessar sua conta.');
    }

    public function login(Request $request): void
    {
        $usuario = $this->auth->login($request->all(), $request->ip());
        $this->success('Login realizado com sucesso.', [
            'usuario'    => $usuario,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function logout(Request $request): void
    {
        $this->auth->logout(Auth::id(), $request->ip());
        $this->success('Sessao encerrada.');
    }

    public function esqueciSenha(Request $request): void
    {
        $this->auth->esqueciSenha($request->all(), $request->ip());
        // Resposta neutra e exata (nao revela se o e-mail existe).
        $this->success(\App\Services\AuthService::MSG_RECUPERACAO_GENERICA);
    }

    public function confirmarCodigo(Request $request): void
    {
        $this->auth->confirmarCodigo($request->all(), $request->ip());
        $this->success('Codigo valido. Defina sua nova senha.');
    }

    public function redefinirSenha(Request $request): void
    {
        $this->auth->redefinirSenha($request->all(), $request->ip());
        $this->success('Senha redefinida com sucesso. Faca login com a nova senha.');
    }

    public function alterarSenha(Request $request): void
    {
        $this->auth->alterarSenha((int) Auth::id(), $request->all(), $request->ip());
        $this->success('Senha alterada com sucesso.');
    }

    public function eu(Request $request): void
    {
        $perfil = $this->usuarios->perfil((int) Auth::id());
        $this->success('Usuario autenticado.', [
            'usuario'    => $perfil,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function csrf(Request $request): void
    {
        $this->success('Token gerado.', ['csrf_token' => Csrf::token()]);
    }
}

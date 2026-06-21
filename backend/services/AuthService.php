<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Validator;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\Usuario;
use App\Repositories\TokenRepository;
use App\Repositories\UsuarioRepository;

/**
 * Regras de autenticacao: cadastro, verificacao de e-mail, login/logout,
 * recuperacao e troca de senha.
 */
final class AuthService
{
    private const VERIFICACAO_HORAS   = 24;
    private const RECUPERACAO_MINUTOS  = 30;
    private const RECUPERACAO_MAX_TENT = 5;

    /** Mensagem generica (anti-enumeracao) da solicitacao de recuperacao de senha. */
    public const MSG_RECUPERACAO_GENERICA =
        'Se existir uma conta vinculada a este e-mail, você receberá as instruções para recuperação.';

    public function __construct(
        private UsuarioRepository $usuarios = new UsuarioRepository(),
        private TokenRepository $tokens = new TokenRepository(),
        private EmailService $email = new EmailService(),
        private LogService $log = new LogService()
    ) {
    }

    /**
     * Cadastra um novo usuario e dispara o e-mail de verificacao.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function registrar(array $dados, string $ip): array
    {
        Validator::make($dados, [
            'nome'             => 'required|string|max:80',
            'sobrenome'        => 'required|string|max:80',
            'email'            => 'required|email|max:160',
            'telefone'         => 'telefone',
            'senha'            => 'required|string|min:8|max:72',
            'senha_confirmacao'=> 'required|same:senha',
        ], [
            'nome'      => 'nome',
            'sobrenome' => 'sobrenome',
            'email'     => 'e-mail',
            'telefone'  => 'telefone',
            'senha'     => 'senha',
            'senha_confirmacao' => 'confirmacao de senha',
        ])->validate();

        $email = strtolower(trim((string) $dados['email']));
        if ($this->usuarios->emailExiste($email)) {
            throw new ValidationException(['email' => ['Este e-mail ja esta cadastrado.']]);
        }

        $usuarioId = $this->usuarios->criar([
            'nome'      => Security::stripTags((string) $dados['nome']),
            'sobrenome' => Security::stripTags((string) $dados['sobrenome']),
            'email'     => $email,
            'telefone'  => isset($dados['telefone']) ? preg_replace('/\D/', '', (string) $dados['telefone']) : null,
            'senha_hash'=> password_hash((string) $dados['senha'], PASSWORD_DEFAULT),
            'role'      => 'cliente',
            'status'    => 'pendente',
        ]);

        $token   = bin2hex(random_bytes(32));
        $expira  = (new \DateTimeImmutable('+' . self::VERIFICACAO_HORAS . ' hours'))->format('Y-m-d H:i:s');
        $this->tokens->criarTokenVerificacao($usuarioId, $token, $expira);

        $this->email->enviarVerificacao($email, (string) $dados['nome'], $token);
        $this->log->registrar('cadastro', $usuarioId, $ip, 'info', 'Novo cadastro de cliente.');

        return [
            'id'    => $usuarioId,
            'email' => $email,
        ];
    }

    /** Confirma o e-mail a partir do token enviado. */
    public function verificarEmail(string $token, string $ip): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new ValidationException(['token' => ['Token de verificacao ausente.']]);
        }

        $registro = $this->tokens->buscarTokenVerificacao($token);
        if ($registro === null) {
            throw new HttpException('Token de verificacao invalido.', 400);
        }

        if (strtotime((string) $registro['expira_em']) < time()) {
            throw new HttpException('Token de verificacao expirado.', 400);
        }

        $usuarioId = (int) $registro['usuario_id'];
        $this->usuarios->marcarEmailVerificado($usuarioId);
        $this->tokens->marcarVerificacaoUsado((int) $registro['id']);
        $this->log->registrar('verificacao_email', $usuarioId, $ip, 'info', 'E-mail verificado.');
    }

    /**
     * Autentica o usuario. Em caso de sucesso, inicia a sessao.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function login(array $dados, string $ip): array
    {
        Validator::make($dados, [
            'email' => 'required|email',
            'senha' => 'required|string',
        ], [
            'email' => 'e-mail',
            'senha' => 'senha',
        ])->validate();

        $email   = strtolower(trim((string) $dados['email']));
        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || !password_verify((string) $dados['senha'], $usuario->senha_hash)) {
            $this->log->registrar('login_falha', $usuario?->id, $ip, 'warning', 'Tentativa de login invalida para ' . $email);
            throw new HttpException('E-mail ou senha incorretos.', 401);
        }

        if (!$usuario->email_verificado) {
            throw new HttpException('Confirme seu e-mail antes de acessar a conta.', 403);
        }

        if ($usuario->status === 'bloqueado') {
            throw new HttpException('Sua conta esta bloqueada. Entre em contato com o suporte.', 403);
        }

        Auth::login([
            'id'    => $usuario->id,
            'role'  => $usuario->role,
            'nome'  => $usuario->nome,
            'email' => $usuario->email,
        ]);

        $this->log->registrar('login', $usuario->id, $ip, 'info', 'Login realizado com sucesso.');

        return $usuario->toArray();
    }

    public function logout(?int $usuarioId, string $ip): void
    {
        if ($usuarioId !== null) {
            $this->log->registrar('logout', $usuarioId, $ip, 'info', 'Logout realizado.');
        }
        Auth::logout();
    }

    /**
     * Inicia recuperacao de senha. Por seguranca, nao revela se o e-mail existe.
     *
     * @param array<string,mixed> $dados
     */
    public function esqueciSenha(array $dados, string $ip): void
    {
        Validator::make($dados, [
            'email' => 'required|email',
        ], ['email' => 'e-mail'])->validate();

        $email   = strtolower(trim((string) $dados['email']));
        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || $usuario->id === null) {
            // Resposta identica para e-mail existente ou nao (anti-enumeracao).
            $this->log->registrar('recuperacao_solicitada', null, $ip, 'warning', 'Solicitacao para e-mail inexistente: ' . $email);
            return;
        }

        $codigo     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);
        $expira     = (new \DateTimeImmutable('+' . self::RECUPERACAO_MINUTOS . ' minutes'))->format('Y-m-d H:i:s');

        $this->tokens->criarTokenRecuperacao($usuario->id, $codigoHash, $expira);
        $this->email->enviarCodigoRecuperacao($email, $usuario->nome, $codigo);
        $this->log->registrar('recuperacao_solicitada', $usuario->id, $ip, 'info', 'Codigo de recuperacao enviado.');
    }

    /**
     * Confere o codigo de recuperacao sem consumi-lo (etapa de confirmacao).
     *
     * @param array<string,mixed> $dados
     */
    public function confirmarCodigo(array $dados, string $ip): void
    {
        Validator::make($dados, [
            'email'  => 'required|email',
            'codigo' => 'required|string',
        ], ['email' => 'e-mail', 'codigo' => 'codigo'])->validate();

        $this->validarCodigo(
            strtolower(trim((string) $dados['email'])),
            (string) $dados['codigo'],
            $ip,
            consumir: false
        );
    }

    /**
     * Redefine a senha a partir de um codigo valido.
     *
     * @param array<string,mixed> $dados
     */
    public function redefinirSenha(array $dados, string $ip): void
    {
        Validator::make($dados, [
            'email'             => 'required|email',
            'codigo'            => 'required|string',
            'senha'             => 'required|string|min:8|max:72',
            'senha_confirmacao' => 'required|same:senha',
        ], [
            'email'             => 'e-mail',
            'codigo'            => 'codigo',
            'senha'             => 'senha',
            'senha_confirmacao' => 'confirmacao de senha',
        ])->validate();

        $usuario = $this->validarCodigo(
            strtolower(trim((string) $dados['email'])),
            (string) $dados['codigo'],
            $ip,
            consumir: true
        );

        $this->usuarios->atualizarSenha($usuario->id, password_hash((string) $dados['senha'], PASSWORD_DEFAULT));
        $this->log->registrar('senha_redefinida', $usuario->id, $ip, 'info', 'Senha redefinida via recuperacao.');
    }

    /**
     * Troca de senha autenticada (area do cliente).
     *
     * @param array<string,mixed> $dados
     */
    public function alterarSenha(int $usuarioId, array $dados, string $ip): void
    {
        Validator::make($dados, [
            'senha_atual'       => 'required|string',
            'senha'             => 'required|string|min:8|max:72',
            'senha_confirmacao' => 'required|same:senha',
        ], [
            'senha_atual'       => 'senha atual',
            'senha'             => 'nova senha',
            'senha_confirmacao' => 'confirmacao de senha',
        ])->validate();

        $usuario = $this->usuarios->buscarPorId($usuarioId);
        if ($usuario === null) {
            throw new HttpException('Usuario nao encontrado.', 404);
        }

        if (!password_verify((string) $dados['senha_atual'], $usuario->senha_hash)) {
            throw new ValidationException(['senha_atual' => ['A senha atual esta incorreta.']]);
        }

        $this->usuarios->atualizarSenha($usuarioId, password_hash((string) $dados['senha'], PASSWORD_DEFAULT));
        $this->log->registrar('senha_alterada', $usuarioId, $ip, 'info', 'Senha alterada na area do cliente.');
    }

    /**
     * Valida um codigo de recuperacao. Se $consumir, marca o token como usado.
     */
    private function validarCodigo(string $email, string $codigo, string $ip, bool $consumir): Usuario
    {
        $codigo  = preg_replace('/\D/', '', $codigo) ?? '';
        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || $usuario->id === null) {
            throw new HttpException('Codigo invalido.', 400);
        }

        $registro = $this->tokens->recuperacaoAtivaPorUsuario($usuario->id);
        if ($registro === null) {
            throw new HttpException('Codigo invalido.', 400);
        }

        if ((int) $registro['tentativas'] >= self::RECUPERACAO_MAX_TENT) {
            $this->tokens->marcarRecuperacaoUsado((int) $registro['id']);
            throw new HttpException('Numero de tentativas excedido. Solicite um novo codigo.', 429);
        }

        if (strtotime((string) $registro['expira_em']) < time()) {
            throw new HttpException('Codigo expirado. Solicite um novo codigo.', 400);
        }

        if (!password_verify($codigo, (string) $registro['codigo_hash'])) {
            $this->tokens->incrementarTentativas((int) $registro['id']);
            $this->log->registrar('recuperacao_codigo_invalido', $usuario->id, $ip, 'warning', 'Codigo de recuperacao incorreto.');
            throw new HttpException('Codigo invalido.', 400);
        }

        if ($consumir) {
            $this->tokens->marcarRecuperacaoUsado((int) $registro['id']);
        }

        return $usuario;
    }
}

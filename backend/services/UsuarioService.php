<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Helpers\Security;
use App\Repositories\EnderecoRepository;
use App\Repositories\UsuarioRepository;

/**
 * Dados da area do cliente: perfil e enderecos.
 */
final class UsuarioService
{
    public function __construct(
        private UsuarioRepository $usuarios = new UsuarioRepository(),
        private EnderecoRepository $enderecos = new EnderecoRepository(),
        private LogService $log = new LogService()
    ) {
    }

    /** @return array<string,mixed> */
    public function perfil(int $usuarioId): array
    {
        $usuario = $this->usuarios->buscarPorId($usuarioId);
        if ($usuario === null) {
            throw new HttpException('Usuario nao encontrado.', 404);
        }
        return $usuario->toArray();
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function atualizarPerfil(int $usuarioId, array $dados, string $ip): array
    {
        Validator::make($dados, [
            'nome'      => 'required|string|max:80',
            'sobrenome' => 'required|string|max:80',
            'telefone'  => 'telefone',
        ], [
            'nome'      => 'nome',
            'sobrenome' => 'sobrenome',
            'telefone'  => 'telefone',
        ])->validate();

        $this->usuarios->atualizarPerfil(
            $usuarioId,
            Security::stripTags((string) $dados['nome']),
            Security::stripTags((string) $dados['sobrenome']),
            isset($dados['telefone']) ? (preg_replace('/\D/', '', (string) $dados['telefone']) ?: null) : null
        );

        $this->log->registrar('perfil_atualizado', $usuarioId, $ip, 'info', 'Dados de perfil atualizados.');

        return $this->perfil($usuarioId);
    }

    /** @return array<int,array<string,mixed>> */
    public function enderecos(int $usuarioId): array
    {
        return array_map(
            static fn ($e) => $e->toArray(),
            $this->enderecos->listarPorUsuario($usuarioId)
        );
    }
}

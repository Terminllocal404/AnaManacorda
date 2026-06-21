<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogRepository;
use Throwable;

/**
 * Camada de registro de logs de sistema. Nunca propaga excecoes:
 * uma falha de log jamais deve interromper a operacao principal.
 */
final class LogService
{
    public function __construct(
        private LogRepository $logs = new LogRepository()
    ) {
    }

    /** @param array<string,mixed>|null $contexto */
    public function registrar(
        string $acao,
        ?int $usuarioId,
        string $ip,
        string $nivel = 'info',
        ?string $descricao = null,
        ?array $contexto = null
    ): void {
        try {
            $this->logs->registrar($acao, $usuarioId, $ip, $nivel, $descricao, $contexto);
        } catch (Throwable $e) {
            // Fallback para arquivo: garante rastreabilidade mesmo sem banco.
            $linha = sprintf(
                "[%s] %s | acao=%s | usuario=%s | ip=%s | %s%s",
                now(),
                strtoupper($nivel),
                $acao,
                $usuarioId ?? '-',
                $ip,
                $descricao ?? '',
                PHP_EOL
            );
            @file_put_contents(storage_path('../logs/fallback.log'), $linha, FILE_APPEND | LOCK_EX);
        }
    }

    /** @param array<string,mixed>|null $contexto */
    public function erro(string $acao, ?int $usuarioId, string $ip, string $descricao, ?array $contexto = null): void
    {
        $this->registrar($acao, $usuarioId, $ip, 'error', $descricao, $contexto);
    }
}

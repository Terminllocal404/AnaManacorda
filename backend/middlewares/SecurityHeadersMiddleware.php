<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;

/**
 * Aplica cabecalhos de seguranca em todas as respostas.
 *
 * Como esta API responde JSON (e nao HTML), a Content-Security-Policy pode ser
 * bastante restritiva. Os cabecalhos sao aplicados no front controller, antes
 * do roteamento, garantindo cobertura inclusive em respostas de erro e 404.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Conjunto de cabecalhos de seguranca aplicados globalmente.
     *
     * @return array<string,string>
     */
    public static function cabecalhos(): array
    {
        return [
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'no-referrer',
            'Content-Security-Policy'   => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
            'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=(), payment=()',
        ];
    }

    public function handle(Request $request): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::cabecalhos() as $nome => $valor) {
            header($nome . ': ' . $valor);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PedidoRepository;
use RuntimeException;

/**
 * Geracao de codigos sequenciais de pedido no formato PED-AAAA-NNNNNN.
 *
 * A sequencia e obtida lendo o ultimo codigo do ano corrente COM LOCK
 * (FOR UPDATE) dentro de uma transacao ja aberta pelo PedidoService,
 * garantindo unicidade sem uso de rand/uniqid/timestamp.
 */
final class CodigoService
{
    private const PREFIXO = 'PED';

    public function __construct(
        private PedidoRepository $pedidos = new PedidoRepository()
    ) {
    }

    /**
     * Gera o proximo codigo sequencial. Deve ser chamado dentro de uma
     * transacao ativa para que o lock de leitura seja efetivo.
     */
    public function gerarCodigoPedido(): string
    {
        $ano     = date('Y');
        $prefixo = self::PREFIXO . '-' . $ano . '-';
        $ultimo  = $this->pedidos->ultimoCodigoComLock($prefixo);

        return self::proximoCodigo($ultimo, $ano);
    }

    /**
     * Logica pura de geracao sequencial, isolada do acesso ao banco para ser
     * testavel de forma deterministica.
     *
     * @param string|null $ultimo ultimo codigo gerado no ano (ou null se nao houver)
     * @param string|null $ano    ano de referencia (default: ano corrente)
     */
    public static function proximoCodigo(?string $ultimo, ?string $ano = null): string
    {
        $ano     = $ano ?? date('Y');
        $prefixo = self::PREFIXO . '-' . $ano . '-';

        $sequencia = 1;
        if ($ultimo !== null) {
            $partes = explode('-', $ultimo);
            $numero = (int) end($partes);
            if ($numero <= 0) {
                throw new RuntimeException('Codigo de pedido existente em formato invalido: ' . $ultimo);
            }
            $sequencia = $numero + 1;
        }

        return $prefixo . str_pad((string) $sequencia, 6, '0', STR_PAD_LEFT);
    }
}

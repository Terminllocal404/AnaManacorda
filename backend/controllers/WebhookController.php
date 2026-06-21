<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\MercadoPagoService;
use App\Services\PagamentoService;

/**
 * Recebe webhooks do Mercado Pago em /api/webhooks/mercadopago.
 *
 * Esta rota e isenta de CSRF (a autenticidade e garantida pela assinatura
 * HMAC enviada no cabecalho x-signature).
 */
final class WebhookController extends Controller
{
    public function __construct(
        private PagamentoService $pagamentos = new PagamentoService(),
        private MercadoPagoService $mercadoPago = new MercadoPagoService()
    ) {
    }

    public function mercadopago(Request $request): void
    {
        // O id do recurso vem em "data.id" (query) ou no corpo {data:{id}}.
        $dataId = (string) ($request->query('data.id') ?? '');
        if ($dataId === '') {
            $data   = $request->input('data');
            $dataId = is_array($data) ? (string) ($data['id'] ?? '') : '';
        }

        $tipo = (string) ($request->query('type') ?? $request->input('type') ?? '');

        $xSignature = $request->header('X-Signature') ?? '';
        $xRequestId = $request->header('X-Request-Id') ?? '';

        // Valida a assinatura antes de qualquer processamento.
        if ($dataId === '' || !$this->mercadoPago->validarAssinaturaWebhook($xSignature, $xRequestId, $dataId)) {
            $this->error('Assinatura de webhook invalida.', 401);
            return;
        }

        $resultado = $this->pagamentos->processarWebhook($tipo, $dataId, $request->ip());

        // O Mercado Pago espera 200/201 para considerar a notificacao entregue.
        $this->success('Webhook recebido.', $resultado);
    }
}

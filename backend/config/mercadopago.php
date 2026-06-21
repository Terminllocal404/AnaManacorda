<?php

declare(strict_types=1);

/**
 * Configuracao da integracao com o Mercado Pago.
 */
return [
    'access_token'   => env('MP_ACCESS_TOKEN', ''),
    'public_key'     => env('MP_PUBLIC_KEY', ''),
    'webhook_secret' => env('MP_WEBHOOK_SECRET', ''),
    'runtime'        => env('MP_RUNTIME', 'SERVER'),
    'pix_expiration_minutes' => (int) env('MP_PIX_EXPIRATION_MINUTES', 30),
    'notification_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api/webhooks/mercadopago',
];

<?php

declare(strict_types=1);

/**
 * Configuracao de envio de e-mail (PHPMailer).
 */
return [
    'driver'     => env('MAIL_DRIVER', 'smtp'),
    'host'       => env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 587),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'nao-responda@anamanacorda.com.br'),
        'name'    => env('MAIL_FROM_NAME', 'Ana Manacorda'),
    ],
];

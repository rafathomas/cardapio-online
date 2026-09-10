<?php

declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Credenciais
    |---------------------------------------------------------------------------
    | Nunca versionar valores reais. Todas as chaves vem do ambiente.
    */
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    'base_url' => rtrim((string) env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'), '/'),
    'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 15),
    'statement_descriptor' => env('MERCADOPAGO_STATEMENT_DESCRIPTOR', 'CARDAPIO'),

    'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),

    'back_urls' => [
        'success' => env('MERCADOPAGO_SUCCESS_URL'),
        'pending' => env('MERCADOPAGO_PENDING_URL'),
        'failure' => env('MERCADOPAGO_FAILURE_URL'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Seguranca do webhook
    |---------------------------------------------------------------------------
    | Quando habilitado, o cabecalho x-signature e validado via HMAC-SHA256
    | antes de qualquer processamento. Manter true em producao.
    */
    'verify_signature' => (bool) env('MERCADOPAGO_VERIFY_SIGNATURE', true),

    // Tolerancia (em segundos) para o timestamp do x-signature.
    'signature_tolerance' => (int) env('MERCADOPAGO_SIGNATURE_TOLERANCE', 600),
];

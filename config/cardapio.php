<?php

declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Gateway de pagamento ativo
    |---------------------------------------------------------------------------
    | Resolvido pelo container atraves de PaymentGatewayInterface.
    */
    'payment_gateway' => env('PAYMENT_GATEWAY', 'mercadopago'),

    'uploads' => [
        'max_image_kb' => (int) env('UPLOAD_MAX_IMAGE_KB', 4096),
        'image_max_width' => (int) env('UPLOAD_IMAGE_MAX_WIDTH', 1280),
        'logo_max_width' => 512,
        'quality' => 82,
    ],

    'branding' => [
        'name' => env('APP_NAME', 'Cardápio Online'),
        'tagline' => 'Seu cardápio online em poucos minutos.',
    ],

    // Slugs reservados que nao podem ser usados por estabelecimentos.
    'reserved_slugs' => [
        'app', 'admin', 'api', 'login', 'register', 'cardapio', 'menu',
        'webhooks', 'sitemap', 'robots', 'storage', 'assets', 'build',
        'planos', 'precos', 'sobre', 'contato', 'termos', 'privacidade',
    ],
];

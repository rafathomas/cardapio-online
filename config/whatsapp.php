<?php

declare(strict_types=1);

return [
    'base_url' => rtrim((string) env('WHATSAPP_BASE_URL', 'https://wa.me'), '/'),
    'default_country_code' => (string) env('WHATSAPP_DEFAULT_COUNTRY_CODE', '55'),
];

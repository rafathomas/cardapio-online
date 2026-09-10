<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Dados necessarios para validar a autenticidade de uma notificacao.
 */
final readonly class WebhookSignatureData
{
    public function __construct(
        public ?string $signatureHeader,
        public ?string $requestId,
        public ?string $resourceId,
    ) {}
}

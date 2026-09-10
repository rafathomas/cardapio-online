<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Money;

/**
 * Intencao de cobranca enviada ao gateway.
 * Montada exclusivamente no backend a partir do plano persistido.
 */
final readonly class PaymentIntentData
{
    public function __construct(
        public Money $amount,
        public string $description,
        public string $externalReference,
        public string $idempotencyKey,
        public string $payerEmail,
        public ?string $payerName = null,
        public ?string $notificationUrl = null,
        public array $backUrls = [],
        public array $metadata = [],
    ) {}
}

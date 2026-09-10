<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Resposta normalizada do gateway. O restante da aplicacao nunca
 * enxerga o formato bruto do Mercado Pago.
 */
final readonly class GatewayPaymentData
{
    public function __construct(
        public PaymentStatus $status,
        public Money $amount,
        public ?string $gatewayPaymentId = null,
        public ?string $preferenceId = null,
        public ?string $externalReference = null,
        public ?string $statusDetail = null,
        public ?string $checkoutUrl = null,
        public ?string $qrCodePayload = null,
        public ?string $paymentMethod = null,
        public ?string $paymentType = null,
        public ?CarbonImmutable $approvedAt = null,
        public array $raw = [],
    ) {}
}

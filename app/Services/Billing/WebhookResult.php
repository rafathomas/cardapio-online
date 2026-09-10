<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\PaymentEvent;

/** Resultado do recebimento de uma notificacao, usado pelo controller. */
final readonly class WebhookResult
{
    private function __construct(
        public string $outcome,
        public int $status,
        public ?PaymentEvent $event = null,
        public ?string $reason = null,
    ) {}

    public static function accepted(PaymentEvent $event): self
    {
        return new self('accepted', 200, $event);
    }

    public static function duplicate(PaymentEvent $event): self
    {
        return new self('duplicate', 200, $event);
    }

    public static function ignored(string $reason): self
    {
        return new self('ignored', 200, null, $reason);
    }

    public static function invalidSignature(): self
    {
        return new self('invalid_signature', 401, null, 'Assinatura inválida.');
    }

    public static function malformed(string $reason): self
    {
        return new self('malformed', 422, null, $reason);
    }
}

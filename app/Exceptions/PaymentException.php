<?php

declare(strict_types=1);

namespace App\Exceptions;

class PaymentException extends DomainException
{
    public static function gatewayUnavailable(string $detail = ''): self
    {
        return new self(
            'Não foi possível comunicar com o meio de pagamento. Tente novamente em instantes.',
            'gateway_unavailable',
            array_filter(['detail' => $detail]),
            502,
        );
    }

    public static function invalidAmount(): self
    {
        return new self('Valor do pagamento inválido.', 'invalid_amount');
    }

    public static function planNotPayable(): self
    {
        return new self('Este plano não exige pagamento.', 'plan_not_payable');
    }

    public static function alreadySubscribed(): self
    {
        return new self('Já existe uma assinatura ativa para este plano.', 'already_subscribed');
    }
}

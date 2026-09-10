<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados da assinatura. Nunca confundir com o estado de um pagamento:
 * um pagamento recusado nao move sozinho a assinatura para "canceled".
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Pending = 'pending';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /** Estados em que o estabelecimento tem direito aos recursos do plano. */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Canceled, self::Expired], true);
    }

    /** Transicoes permitidas a partir deste estado. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Trial, self::Canceled, self::Expired],
            self::Trial => [self::Active, self::Pending, self::PastDue, self::Canceled, self::Expired],
            self::Active => [self::PastDue, self::Canceled, self::Expired, self::Pending],
            self::PastDue => [self::Active, self::Canceled, self::Expired],
            self::Canceled => [self::Pending, self::Active],
            self::Expired => [self::Pending, self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Periodo de teste',
            self::Active => 'Ativa',
            self::PastDue => 'Pagamento em atraso',
            self::Pending => 'Aguardando pagamento',
            self::Canceled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }
}

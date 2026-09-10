<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case InProcess = 'in_process';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case ChargedBack = 'charged_back';

    /**
     * Traduz o status bruto do Mercado Pago para o status interno.
     * Qualquer status desconhecido vira "pending" para nunca liberar acesso por engano.
     */
    public static function fromMercadoPago(?string $status): self
    {
        return match ($status) {
            'approved' => self::Approved,
            'authorized' => self::InProcess,
            'in_process', 'in_mediation' => self::InProcess,
            'rejected' => self::Rejected,
            'cancelled', 'canceled' => self::Canceled,
            'refunded' => self::Refunded,
            'charged_back' => self::ChargedBack,
            default => self::Pending,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Canceled, self::Refunded, self::ChargedBack], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Approved;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::InProcess => 'Em processamento',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::Canceled => 'Cancelado',
            self::Refunded => 'Estornado',
            self::ChargedBack => 'Chargeback',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    public function addTo(CarbonImmutable $date): ?CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $date->addMonthNoOverflow(),
            self::Yearly => $date->addYear(),
            self::Lifetime => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Yearly => 'Anual',
            self::Lifetime => 'Vitalício',
        };
    }
}

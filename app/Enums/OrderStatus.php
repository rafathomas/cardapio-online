<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::Preparing => 'Em preparo',
            self::Ready => 'Pronto',
            self::Delivered => 'Entregue',
            self::Canceled => 'Cancelado',
        };
    }
}

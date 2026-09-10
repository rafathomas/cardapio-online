<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Money;

final readonly class CartAddonLine
{
    public function __construct(
        public int $id,
        public string $name,
        public Money $price,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_cents' => $this->price->cents,
            'price_formatted' => $this->price->format(),
        ];
    }
}

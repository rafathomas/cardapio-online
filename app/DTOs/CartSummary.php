<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Money;

final readonly class CartSummary
{
    /** @param  list<CartLine>  $lines */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $total,
    ) {}

    public function itemCount(): int
    {
        return array_sum(array_map(static fn (CartLine $l): int => $l->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function toArray(): array
    {
        return [
            'items' => array_map(static fn (CartLine $l): array => $l->toArray(), $this->lines),
            'item_count' => $this->itemCount(),
            'subtotal_cents' => $this->subtotal->cents,
            'subtotal_formatted' => $this->subtotal->format(),
            'total_cents' => $this->total->cents,
            'total_formatted' => $this->total->format(),
        ];
    }
}

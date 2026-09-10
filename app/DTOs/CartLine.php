<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Product;
use App\Support\Money;

final readonly class CartLine
{
    /** @param  list<CartAddonLine>  $addons */
    public function __construct(
        public Product $product,
        public int $quantity,
        public Money $unitPrice,
        public array $addons,
        public Money $addonsPerUnit,
        public Money $lineTotal,
        public ?string $notes = null,
    ) {}

    /** Preco unitario ja com adicionais. */
    public function unitTotal(): Money
    {
        return $this->unitPrice->plus($this->addonsPerUnit);
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'name' => $this->product->name,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unitPrice->cents,
            'unit_price_formatted' => $this->unitPrice->format(),
            'addons' => array_map(static fn (CartAddonLine $a): array => $a->toArray(), $this->addons),
            'addons_per_unit_cents' => $this->addonsPerUnit->cents,
            'line_total_cents' => $this->lineTotal->cents,
            'line_total_formatted' => $this->lineTotal->format(),
            'notes' => $this->notes,
        ];
    }
}

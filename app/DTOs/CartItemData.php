<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Item do carrinho como enviado pelo cliente.
 * Contem apenas identificadores e quantidade: precos nunca vem do frontend.
 */
final readonly class CartItemData
{
    /** @param  list<int>  $addonIds */
    public function __construct(
        public int $productId,
        public int $quantity,
        public ?string $notes = null,
        public array $addonIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $addonIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $data['addon_ids'] ?? [],
        )));

        return new self(
            productId: (int) ($data['product_id'] ?? 0),
            quantity: (int) ($data['quantity'] ?? 0),
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            addonIds: $addonIds,
        );
    }

    /** @return list<self> */
    public static function collection(array $items): array
    {
        return array_map(static fn (array $item): self => self::fromArray($item), array_values($items));
    }
}

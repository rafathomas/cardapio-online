<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\DTOs\CartAddonLine;
use App\DTOs\CartItemData;
use App\DTOs\CartLine;
use App\DTOs\CartSummary;
use App\Exceptions\CartException;
use App\Models\Establishment;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Fonte unica de verdade para o valor de um pedido.
 *
 * Precos, adicionais e disponibilidade sao SEMPRE relidos do banco. Nada que
 * venha do frontend influencia o total: o cliente envia apenas ids e quantidades.
 */
class CartCalculator
{
    public const MAX_QUANTITY_PER_ITEM = 99;

    /**
     * @param  list<CartItemData>  $items
     *
     * @throws CartException
     */
    public function calculate(Establishment $establishment, array $items): CartSummary
    {
        if ($items === []) {
            throw CartException::emptyCart();
        }

        $products = $this->loadProducts($establishment, $items);
        $addons = $this->loadAddons($establishment, $items);

        $lines = [];
        $subtotal = Money::zero();

        foreach ($items as $item) {
            if ($item->quantity < 1 || $item->quantity > self::MAX_QUANTITY_PER_ITEM) {
                throw CartException::invalidQuantity();
            }

            $product = $products->get($item->productId);

            if (! $product) {
                throw CartException::productUnavailable($item->productId);
            }

            $addonLines = $this->resolveAddons($product, $item, $addons);

            $addonsPerUnit = array_reduce(
                $addonLines,
                static fn (Money $carry, CartAddonLine $a): Money => $carry->plus($a->price),
                Money::zero(),
            );

            $unitPrice = $product->effectivePrice();
            $lineTotal = $unitPrice->plus($addonsPerUnit)->multipliedBy($item->quantity);

            $lines[] = new CartLine(
                product: $product,
                quantity: $item->quantity,
                unitPrice: $unitPrice,
                addons: $addonLines,
                addonsPerUnit: $addonsPerUnit,
                lineTotal: $lineTotal,
                notes: $item->notes,
            );

            $subtotal = $subtotal->plus($lineTotal);
        }

        return new CartSummary(
            lines: $lines,
            subtotal: $subtotal,
            total: $subtotal,
        );
    }

    /**
     * @param  list<CartItemData>  $items
     * @return Collection<int, Product>
     */
    private function loadProducts(Establishment $establishment, array $items): Collection
    {
        $ids = array_values(array_unique(array_map(
            static fn (CartItemData $i): int => $i->productId,
            $items,
        )));

        return Product::query()
            ->where('establishment_id', $establishment->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with([
                'category',
                'addonGroups' => fn ($q) => $q->where('is_active', true),
            ])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<CartItemData>  $items
     * @return Collection<int, ProductAddon>
     */
    private function loadAddons(Establishment $establishment, array $items): Collection
    {
        $ids = array_values(array_unique(array_merge(
            ...array_map(static fn (CartItemData $i): array => $i->addonIds, $items)
        )));

        if ($ids === []) {
            return collect();
        }

        return ProductAddon::query()
            ->whereIn('product_addons.id', $ids)
            ->where('product_addons.is_active', true)
            ->join('addon_groups', 'addon_groups.id', '=', 'product_addons.addon_group_id')
            ->where('addon_groups.establishment_id', $establishment->id)
            ->where('addon_groups.is_active', true)
            ->select('product_addons.*')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, ProductAddon>  $addons
     * @return list<CartAddonLine>
     */
    private function resolveAddons(Product $product, CartItemData $item, Collection $addons): array
    {
        if ($item->addonIds === []) {
            $this->assertRequiredGroupsSatisfied($product, []);

            return [];
        }

        $allowedGroupIds = $product->addonGroups->pluck('id')->all();
        $lines = [];
        $selectedByGroup = [];

        foreach ($item->addonIds as $addonId) {
            $addon = $addons->get($addonId);

            if (! $addon || ! in_array($addon->addon_group_id, $allowedGroupIds, true)) {
                throw CartException::addonUnavailable($addonId);
            }

            $selectedByGroup[$addon->addon_group_id] = ($selectedByGroup[$addon->addon_group_id] ?? 0) + 1;

            $lines[] = new CartAddonLine(
                id: $addon->id,
                name: $addon->name,
                price: $addon->price(),
            );
        }

        $this->assertRequiredGroupsSatisfied($product, $selectedByGroup);

        return $lines;
    }

    /** @param  array<int, int>  $selectedByGroup */
    private function assertRequiredGroupsSatisfied(Product $product, array $selectedByGroup): void
    {
        foreach ($product->addonGroups as $group) {
            $selected = $selectedByGroup[$group->id] ?? 0;

            $min = $group->is_required ? max(1, $group->min_options) : $group->min_options;

            if ($selected < $min || $selected > $group->max_options) {
                // Grupo opcional sem selecao alguma continua valido.
                if (! $group->is_required && $selected === 0) {
                    continue;
                }

                throw CartException::addonGroupRuleViolated($group->name, $min, $group->max_options);
            }
        }
    }
}

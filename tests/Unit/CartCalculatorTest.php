<?php

declare(strict_types=1);

use App\DTOs\CartItemData;
use App\Exceptions\CartException;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Services\Menu\CartCalculator;

beforeEach(function (): void {
    $this->calculator = app(CartCalculator::class);
    $this->establishment = makeEstablishment();
});

it('soma quantidades pelo preco cadastrado', function (): void {
    $burger = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 2990,
    ]);
    $fries = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 1200,
    ]);

    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $burger->id, 'quantity' => 2],
        ['product_id' => $fries->id, 'quantity' => 1],
    ]));

    expect($summary->subtotal->cents)->toBe(7180)
        ->and($summary->total->cents)->toBe(7180)
        ->and($summary->itemCount())->toBe(3);
});

it('usa o preco promocional quando ele e menor', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 2990,
        'promo_price_cents' => 1990,
    ]);

    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 2],
    ]));

    expect($summary->total->cents)->toBe(3980);
});

it('ignora promocao maior ou igual ao preco cheio', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 2000,
        'promo_price_cents' => 2500,
    ]);

    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1],
    ]));

    expect($summary->total->cents)->toBe(2000);
});

it('soma adicionais por unidade', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 2990,
    ]);

    $group = AddonGroup::factory()->create(['establishment_id' => $this->establishment->id]);
    $cheese = ProductAddon::factory()->create(['addon_group_id' => $group->id, 'name' => 'Queijo', 'price_cents' => 300]);
    $bacon = ProductAddon::factory()->create(['addon_group_id' => $group->id, 'name' => 'Bacon', 'price_cents' => 500]);

    $product->addonGroups()->attach($group);

    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 2, 'addon_ids' => [$cheese->id, $bacon->id]],
    ]));

    // (29,90 + 3,00 + 5,00) * 2
    expect($summary->total->cents)->toBe(7580);
});

it('recusa carrinho vazio', function (): void {
    $this->calculator->calculate($this->establishment, []);
})->throws(CartException::class);

it('recusa produto inativo', function (): void {
    $product = Product::factory()->inactive()->create([
        'establishment_id' => $this->establishment->id,
    ]);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1],
    ]));
})->throws(CartException::class);

it('recusa produto inexistente', function (): void {
    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => 999999, 'quantity' => 1],
    ]));
})->throws(CartException::class);

it('recusa produto de outro estabelecimento', function (): void {
    $other = makeEstablishment();
    $product = Product::factory()->create(['establishment_id' => $other->id]);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1],
    ]));
})->throws(CartException::class);

it('recusa adicional de outro estabelecimento', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);
    $group = AddonGroup::factory()->create(['establishment_id' => $this->establishment->id]);
    $product->addonGroups()->attach($group);

    $other = makeEstablishment();
    $otherGroup = AddonGroup::factory()->create(['establishment_id' => $other->id]);
    $foreignAddon = ProductAddon::factory()->create(['addon_group_id' => $otherGroup->id]);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1, 'addon_ids' => [$foreignAddon->id]],
    ]));
})->throws(CartException::class);

it('recusa adicional que nao pertence ao produto', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);

    // Grupo existe no estabelecimento, mas nao esta vinculado a este produto.
    $group = AddonGroup::factory()->create(['establishment_id' => $this->establishment->id]);
    $addon = ProductAddon::factory()->create(['addon_group_id' => $group->id]);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1, 'addon_ids' => [$addon->id]],
    ]));
})->throws(CartException::class);

it('recusa quantidade invalida', function (int $quantity): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => $quantity],
    ]));
})->with([0, -3, 100])->throws(CartException::class);

it('exige a selecao minima de um grupo obrigatorio', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);
    $group = AddonGroup::factory()->required(min: 1, max: 1)->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Escolha o ponto',
    ]);
    ProductAddon::factory()->create(['addon_group_id' => $group->id]);
    $product->addonGroups()->attach($group);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1],
    ]));
})->throws(CartException::class);

it('recusa mais adicionais que o maximo do grupo', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);
    $group = AddonGroup::factory()->create([
        'establishment_id' => $this->establishment->id,
        'max_options' => 1,
    ]);
    $a = ProductAddon::factory()->create(['addon_group_id' => $group->id]);
    $b = ProductAddon::factory()->create(['addon_group_id' => $group->id]);
    $product->addonGroups()->attach($group);

    $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1, 'addon_ids' => [$a->id, $b->id]],
    ]));
})->throws(CartException::class);

it('preserva a observacao de cada item', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);

    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1, 'notes' => 'Sem cebola'],
    ]));

    expect($summary->lines[0]->notes)->toBe('Sem cebola');
});

it('nao aceita preco vindo do cliente', function (): void {
    $category = Category::factory()->create(['establishment_id' => $this->establishment->id]);
    $product = Product::factory()->forCategory($category)->create(['price_cents' => 5000]);

    // O cliente tenta injetar um preco de 1 centavo.
    $summary = $this->calculator->calculate($this->establishment, CartItemData::collection([
        ['product_id' => $product->id, 'quantity' => 1, 'price_cents' => 1, 'price' => 0.01],
    ]));

    expect($summary->total->cents)->toBe(5000);
});

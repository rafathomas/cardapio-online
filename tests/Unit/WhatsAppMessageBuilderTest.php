<?php

declare(strict_types=1);

use App\DTOs\CartItemData;
use App\DTOs\CartSummary;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Services\Menu\CartCalculator;
use App\Services\Menu\WhatsAppMessageBuilder;

beforeEach(function (): void {
    $this->builder = app(WhatsAppMessageBuilder::class);
    $this->calculator = app(CartCalculator::class);
    $this->establishment = makeEstablishment(attributes: [
        'name' => 'Sabor & Ponto',
        'whatsapp' => '11988887777',
    ]);
});

function cartFor(array $items): CartSummary
{
    return app(CartCalculator::class)->calculate(
        test()->establishment,
        CartItemData::collection($items),
    );
}

it('monta a mensagem no formato esperado', function (): void {
    $burgers = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Hambúrgueres',
    ]);
    $sides = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Porções',
    ]);

    $xBacon = Product::factory()->forCategory($burgers)->create([
        'name' => 'X-Bacon',
        'price_cents' => 2990,
    ]);
    $fries = Product::factory()->forCategory($sides)->create([
        'name' => 'Batata P',
        'price_cents' => 1200,
    ]);

    $cart = cartFor([
        ['product_id' => $xBacon->id, 'quantity' => 2],
        ['product_id' => $fries->id, 'quantity' => 1],
    ]);

    $message = $this->builder->build($this->establishment, $cart, [
        'name' => 'Mariana',
        'notes' => 'Sem cebola.',
    ]);

    expect($message)
        ->toContain('Olá! Gostaria de fazer um pedido:')
        ->toContain('🍔 2x X-Bacon — R$ 59,80')
        ->toContain('🍟 1x Batata P — R$ 12,00')
        ->toContain('*Total: R$ 71,80*')
        ->toContain('*Cliente:* Mariana')
        ->toContain('Observação:')
        ->toContain('Sem cebola.');
});

it('lista os adicionais e a observacao de cada item', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'X-Tudo',
        'price_cents' => 3000,
    ]);

    $group = AddonGroup::factory()->create(['establishment_id' => $this->establishment->id]);
    $bacon = ProductAddon::factory()->create([
        'addon_group_id' => $group->id,
        'name' => 'Bacon',
        'price_cents' => 500,
    ]);
    $product->addonGroups()->attach($group);

    $cart = cartFor([
        ['product_id' => $product->id, 'quantity' => 1, 'addon_ids' => [$bacon->id], 'notes' => 'Bem passado'],
    ]);

    $message = $this->builder->build($this->establishment, $cart, ['name' => 'João']);

    expect($message)
        ->toContain('Bacon (+R$ 5,00)')
        ->toContain('📝 Bem passado')
        ->toContain('*Total: R$ 35,00*');
});

it('gera a url do whatsapp com a mensagem codificada', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Combo',
        'price_cents' => 1000,
    ]);

    $cart = cartFor([['product_id' => $product->id, 'quantity' => 1]]);

    $url = $this->builder->buildUrl($this->establishment, $cart, ['name' => 'Ana']);

    expect($url)->toStartWith(config('whatsapp.base_url').'/5511988887777?text=')
        ->and($url)->not->toContain(' ')
        ->and($url)->not->toContain("\n");

    $decoded = rawurldecode(parse_url($url, PHP_URL_QUERY));
    expect($decoded)->toContain('Olá! Gostaria de fazer um pedido:');
});

it('normaliza numeros de whatsapp em formatos variados', function (string $stored, string $expected): void {
    $establishment = makeEstablishment(attributes: ['whatsapp' => $stored]);

    expect($establishment->whatsappNumber())->toBe($expected);
})->with([
    ['11988887777', '5511988887777'],
    ['(11) 98888-7777', '5511988887777'],
    ['+55 11 98888-7777', '5511988887777'],
    ['5511988887777', '5511988887777'],
]);

it('inclui tipo de entrega e endereco quando informados', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 1000,
    ]);

    $cart = cartFor([['product_id' => $product->id, 'quantity' => 1]]);

    $message = $this->builder->build($this->establishment, $cart, [
        'name' => 'Carlos',
        'delivery_type' => 'delivery',
        'address' => 'Rua das Flores, 120',
        'payment_method' => 'Pix',
    ]);

    expect($message)
        ->toContain('*Tipo:* Entrega')
        ->toContain('*Endereço:* Rua das Flores, 120')
        ->toContain('*Pagamento:* Pix');
});


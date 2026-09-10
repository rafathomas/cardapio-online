<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;

beforeEach(function (): void {
    $this->establishment = makeEstablishment(attributes: [
        'slug' => 'sabor-e-ponto',
        'whatsapp' => '11988887777',
        'manual_status' => 'open',
    ]);

    $this->category = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Hambúrgueres',
    ]);

    $this->burger = Product::factory()->forCategory($this->category)->create([
        'name' => 'X-Bacon',
        'price_cents' => 2990,
    ]);
});

function orderPayload(array $items, array $customer = []): array
{
    return [
        'customer' => array_merge([
            'name' => 'Mariana Costa',
            'phone' => '11987654321',
            'delivery_type' => 'pickup',
        ], $customer),
        'items' => $items,
    ];
}

it('calcula o carrinho no servidor', function (): void {
    $this->postJson(route('api.menu.cart.preview', 'sabor-e-ponto'), [
        'items' => [['product_id' => $this->burger->id, 'quantity' => 2]],
    ])
        ->assertOk()
        ->assertJsonPath('data.total_cents', 5980)
        ->assertJsonPath('data.total_formatted', 'R$ 59,80')
        ->assertJsonPath('data.item_count', 2);
});

it('registra o pedido e devolve a url do whatsapp', function (): void {
    $response = $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 2, 'notes' => 'Sem cebola'],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.total_cents', 5980)
        ->assertJsonPath('data.total_formatted', 'R$ 59,80');

    expect($response->json('whatsapp_url'))
        ->toStartWith(config('whatsapp.base_url').'/5511988887777?text=');

    $order = Order::firstOrFail();

    expect($order->establishment_id)->toBe($this->establishment->id)
        ->and($order->total_cents)->toBe(5980)
        ->and($order->status)->toBe(OrderStatus::Received)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe('X-Bacon')
        ->and($order->items->first()->notes)->toBe('Sem cebola')
        ->and($order->whatsapp_message)->toContain('X-Bacon');
});

it('grava snapshot do item: alterar o produto depois nao muda o pedido', function (): void {
    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertCreated();

    $this->burger->update(['name' => 'Renomeado', 'price_cents' => 9900]);

    $item = Order::first()->items->first();

    expect($item->product_name)->toBe('X-Bacon')
        ->and($item->unit_price_cents)->toBe(2990);
});

it('ignora preco enviado pelo cliente', function (): void {
    $response = $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), [
        'customer' => ['name' => 'Fraudador', 'delivery_type' => 'pickup'],
        'items' => [[
            'product_id' => $this->burger->id,
            'quantity' => 1,
            // Tentativa de manipular o preco no frontend.
            'price' => 0.01,
            'price_cents' => 1,
            'total_cents' => 1,
        ]],
        'total_cents' => 1,
        'subtotal_cents' => 1,
    ]);

    $response->assertCreated()->assertJsonPath('data.total_cents', 2990);

    expect(Order::first()->total_cents)->toBe(2990);
});

it('soma adicionais validos', function (): void {
    $group = AddonGroup::factory()->create(['establishment_id' => $this->establishment->id]);
    $bacon = ProductAddon::factory()->create([
        'addon_group_id' => $group->id,
        'name' => 'Bacon',
        'price_cents' => 500,
    ]);
    $this->burger->addonGroups()->attach($group);

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 2, 'addon_ids' => [$bacon->id]],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.total_cents', 6980);

    expect(Order::first()->items->first()->addons)->toHaveCount(1);
});

it('recusa pedido com o estabelecimento fechado', function (): void {
    $this->establishment->update(['manual_status' => 'closed']);

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'establishment_closed');

    expect(Order::count())->toBe(0);
});

it('recusa pedido em cardapio nao publicado', function (): void {
    $this->establishment->update(['is_published' => false]);

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertStatus(404);
});

it('recusa pedido de estabelecimento inexistente', function (): void {
    $this->postJson(route('api.menu.orders.store', 'nao-existe'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertStatus(404);
});

it('recusa produto de outro estabelecimento', function (): void {
    $alheio = makeEstablishment();
    $produtoAlheio = Product::factory()->create(['establishment_id' => $alheio->id]);

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $produtoAlheio->id, 'quantity' => 1],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'product_unavailable');
});

it('recusa produto inativo', function (): void {
    $this->burger->update(['is_active' => false]);

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertStatus(422);
});

it('valida os dados do cliente', function (array $payload, string $field): void {
    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'sem nome' => [[
        'customer' => ['delivery_type' => 'pickup'],
        'items' => [['product_id' => 1, 'quantity' => 1]],
    ], 'customer.name'],
    'carrinho vazio' => [[
        'customer' => ['name' => 'Ana', 'delivery_type' => 'pickup'],
        'items' => [],
    ], 'items'],
    'quantidade zero' => [[
        'customer' => ['name' => 'Ana', 'delivery_type' => 'pickup'],
        'items' => [['product_id' => 1, 'quantity' => 0]],
    ], 'items.0.quantity'],
    'entrega sem endereco' => [[
        'customer' => ['name' => 'Ana', 'delivery_type' => 'delivery'],
        'items' => [['product_id' => 1, 'quantity' => 1]],
    ], 'customer.address'],
    'tipo de entrega invalido' => [[
        'customer' => ['name' => 'Ana', 'delivery_type' => 'teletransporte'],
        'items' => [['product_id' => 1, 'quantity' => 1]],
    ], 'customer.delivery_type'],
]);

it('gera codigos de pedido distintos', function (): void {
    foreach (range(1, 3) as $ignored) {
        $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
            ['product_id' => $this->burger->id, 'quantity' => 1],
        ]))->assertCreated();
    }

    expect(Order::pluck('code')->unique())->toHaveCount(3);
});

it('nao exige autenticacao para pedir', function (): void {
    $this->assertGuest();

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertCreated();
});

it('aplica rate limit em pedidos', function (): void {
    foreach (range(1, 20) as $ignored) {
        $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
            ['product_id' => $this->burger->id, 'quantity' => 1],
        ]));
    }

    $this->postJson(route('api.menu.orders.store', 'sabor-e-ponto'), orderPayload([
        ['product_id' => $this->burger->id, 'quantity' => 1],
    ]))->assertStatus(429);
});

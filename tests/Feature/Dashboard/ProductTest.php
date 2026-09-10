<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->establishment = makeEstablishment(owner: $this->user);
    $this->category = Category::factory()->create(['establishment_id' => $this->establishment->id]);
});

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'X-Bacon',
        'description' => 'Delicioso',
        'price' => '29,90',
        'category_id' => test()->category->id,
    ], $overrides);
}

it('cria produto convertendo o preco para centavos', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload())
        ->assertCreated()
        ->assertJsonPath('data.price_cents', 2990)
        ->assertJsonPath('data.price_formatted', 'R$ 29,90')
        ->assertJsonPath('data.slug', 'x-bacon');

    $this->assertDatabaseHas('products', [
        'establishment_id' => $this->establishment->id,
        'price_cents' => 2990,
    ]);
});

it('aceita preco em formato decimal com ponto', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload(['price' => 15.5]))
        ->assertCreated()
        ->assertJsonPath('data.price_cents', 1550);
});

it('recusa preco invalido', function (mixed $price): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload(['price' => $price]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('price');
})->with([
    'negativo' => [-10],
    'texto' => ['gratis'],
    'acima do teto' => [999999.99],
]);

it('exige preco', function (): void {
    $payload = productPayload();
    unset($payload['price']);

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('price');
});

it('recusa promocao maior ou igual ao preco', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload([
            'price' => 20.00,
            'promo_price' => 25.00,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('promo_price');
});

it('aceita promocao valida', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload([
            'price' => 30.00,
            'promo_price' => 19.90,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.promo_price_cents', 1990)
        ->assertJsonPath('data.effective_price_cents', 1990)
        ->assertJsonPath('data.has_discount', true);
});

it('recusa categoria de outro estabelecimento', function (): void {
    $alheia = Category::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload(['category_id' => $alheia->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('recusa categoria inexistente', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload(['category_id' => 999999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

it('bloqueia o 11o produto no plano free', function (): void {
    Product::factory()->count(10)->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload())
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'plan_limit_products');

    expect($this->establishment->products()->count())->toBe(10);
});

it('permite o decimo produto no plano free', function (): void {
    Product::factory()->count(9)->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $this->establishment), productPayload())
        ->assertCreated();

    expect($this->establishment->products()->count())->toBe(10);
});

it('permite produtos ilimitados no plano pro', function (): void {
    $pro = makeEstablishment(proPlan(), owner: $this->user);
    Product::factory()->count(50)->create(['establishment_id' => $pro->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $pro), [
            'name' => 'Mais um',
            'price' => 10.00,
        ])
        ->assertCreated();
});

it('informa o limite restante do plano na listagem', function (): void {
    Product::factory()->count(4)->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->getJson(route('api.products.index', $this->establishment))
        ->assertOk()
        ->assertJsonPath('plan.limits.products_remaining', 6)
        ->assertJsonPath('plan.limits.max_products', 10);
});

it('filtra produtos por categoria e busca', function (): void {
    $outra = Category::factory()->create(['establishment_id' => $this->establishment->id]);
    Product::factory()->forCategory($this->category)->create(['name' => 'X-Salada']);
    Product::factory()->forCategory($outra)->create(['name' => 'Coca-Cola']);

    $this->actingAs($this->user)
        ->getJson(route('api.products.index', $this->establishment).'?category_id='.$this->category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'X-Salada');

    $this->actingAs($this->user)
        ->getJson(route('api.products.index', $this->establishment).'?search=Coca')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Coca-Cola');
});

it('atualiza o produto', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'price_cents' => 1000,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('api.products.update', [$this->establishment, $product]), productPayload([
            'name' => 'Nome Novo',
            'price' => 45.00,
        ]))
        ->assertOk()
        ->assertJsonPath('data.price_cents', 4500);

    expect($product->refresh()->name)->toBe('Nome Novo');
});

it('exclui o produto', function (): void {
    $product = Product::factory()->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->deleteJson(route('api.products.destroy', [$this->establishment, $product]))
        ->assertOk();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('duplica o produto como inativo', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Original',
        'price_cents' => 2500,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.duplicate', [$this->establishment, $product]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Original (cópia)')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.price_cents', 2500);

    expect($this->establishment->products()->count())->toBe(2);
});

it('respeita o limite do plano ao duplicar', function (): void {
    $products = Product::factory()->count(10)->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.duplicate', [$this->establishment, $products->first()]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'plan_limit_products');
});

it('alterna a disponibilidade', function (): void {
    $product = Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.toggle', [$this->establishment, $product]))
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($product->refresh()->is_active)->toBeFalse();
});

it('reordena os produtos', function (): void {
    $a = Product::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 0]);
    $b = Product::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 1]);

    $this->actingAs($this->user)
        ->postJson(route('api.products.reorder', $this->establishment), ['ids' => [$b->id, $a->id]])
        ->assertOk();

    expect($b->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1);
});

it('envia imagem do produto convertida para webp', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user)->post(route('api.products.store', $this->establishment), productPayload([
        'image' => UploadedFile::fake()->image('produto.jpg', 2000, 1500),
    ]))->assertCreated();

    $path = Product::first()->image_path;

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('impede acessar produto de outro estabelecimento pela url', function (): void {
    $alheio = Product::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.products.show', [$this->establishment, $alheio]))
        ->assertStatus(404);
});

it('impede criar produto em estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->postJson(route('api.products.store', $alheio), ['name' => 'Invasor', 'price' => 10])
        ->assertStatus(403);

    expect($alheio->products()->count())->toBe(0);
});

it('impede excluir produto de outro usuario', function (): void {
    $alheio = makeEstablishment();
    $product = Product::factory()->create(['establishment_id' => $alheio->id]);

    $this->actingAs($this->user)
        ->deleteJson(route('api.products.destroy', [$alheio, $product]))
        ->assertStatus(403);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});

<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->establishment = makeEstablishment(owner: $this->user);
});

it('cria categoria com slug', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $this->establishment), ['name' => 'Hambúrgueres'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'hamburgueres');

    $this->assertDatabaseHas('categories', [
        'establishment_id' => $this->establishment->id,
        'name' => 'Hambúrgueres',
    ]);
});

it('gera slug unico dentro do mesmo estabelecimento', function (): void {
    Category::factory()->create(['establishment_id' => $this->establishment->id, 'slug' => 'bebidas']);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $this->establishment), ['name' => 'Bebidas'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'bebidas-2');
});

it('permite o mesmo slug em estabelecimentos diferentes', function (): void {
    $outro = makeEstablishment(owner: $this->user);
    Category::factory()->create(['establishment_id' => $outro->id, 'slug' => 'bebidas']);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $this->establishment), ['name' => 'Bebidas'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'bebidas');
});

it('valida o nome da categoria', function (array $payload): void {
    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $this->establishment), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
})->with([
    'vazio' => [['name' => '']],
    'curto demais' => [['name' => 'A']],
    'ausente' => [[]],
    'longo demais' => [['name' => str_repeat('a', 200)]],
]);

it('lista categorias com a contagem de produtos', function (): void {
    $category = Category::factory()->create(['establishment_id' => $this->establishment->id]);
    Product::factory()->count(3)->forCategory($category)->create();

    $this->actingAs($this->user)
        ->getJson(route('api.categories.index', $this->establishment))
        ->assertOk()
        ->assertJsonPath('data.0.products_count', 3);
});

it('atualiza a categoria', function (): void {
    $category = Category::factory()->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->putJson(route('api.categories.update', [$this->establishment, $category]), [
            'name' => 'Novo Nome',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo Nome');

    expect($category->refresh()->is_active)->toBeFalse();
});

it('exclui a categoria sem apagar os produtos', function (): void {
    $category = Category::factory()->create(['establishment_id' => $this->establishment->id]);
    $product = Product::factory()->forCategory($category)->create();

    $this->actingAs($this->user)
        ->deleteJson(route('api.categories.destroy', [$this->establishment, $category]))
        ->assertOk();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    expect($product->refresh()->category_id)->toBeNull();
});

it('reordena as categorias', function (): void {
    $a = Category::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 0]);
    $b = Category::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 1]);
    $c = Category::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 2]);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.reorder', $this->establishment), [
            'ids' => [$c->id, $a->id, $b->id],
        ])
        ->assertOk();

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);
});

it('ignora ids de outro estabelecimento ao reordenar', function (): void {
    $minha = Category::factory()->create(['establishment_id' => $this->establishment->id, 'sort_order' => 5]);
    $alheia = Category::factory()->create(['sort_order' => 9]);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.reorder', $this->establishment), [
            'ids' => [$alheia->id, $minha->id],
        ])
        ->assertOk();

    expect($alheia->refresh()->sort_order)->toBe(9)
        ->and($minha->refresh()->sort_order)->toBe(1);
});

it('bloqueia a 6a categoria no plano free', function (): void {
    Category::factory()->count(5)->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $this->establishment), ['name' => 'Extra'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'plan_limit_categories');

    expect($this->establishment->categories()->count())->toBe(5);
});

it('permite categorias ilimitadas no plano pro', function (): void {
    $pro = makeEstablishment(proPlan(), owner: $this->user);
    Category::factory()->count(20)->create(['establishment_id' => $pro->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $pro), ['name' => 'Mais uma'])
        ->assertCreated();
});

it('nao alcanca a categoria de outro estabelecimento pela url', function (): void {
    $alheia = Category::factory()->create();

    // Estabelecimento proprio na URL, categoria de terceiro: precisa dar 404.
    $this->actingAs($this->user)
        ->putJson(route('api.categories.update', [$this->establishment, $alheia]), ['name' => 'Invadida'])
        ->assertStatus(404);

    expect($alheia->refresh()->name)->not->toBe('Invadida');
});

it('impede criar categoria em estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->postJson(route('api.categories.store', $alheio), ['name' => 'Invasora'])
        ->assertStatus(403);

    expect($alheio->categories()->count())->toBe(0);
});

it('retorna 404 para categoria inexistente', function (): void {
    $this->actingAs($this->user)
        ->getJson("/api/establishments/{$this->establishment->id}/categories/999999")
        ->assertStatus(404);
});

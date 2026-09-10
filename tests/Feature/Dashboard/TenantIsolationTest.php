<?php

declare(strict_types=1);

use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

/**
 * Um usuário nunca pode alcançar dados de outro trocando um id na URL.
 * Este arquivo percorre todos os recursos do painel exercitando essa fronteira.
 */
beforeEach(function (): void {
    $this->intruso = User::factory()->create();
    $this->vitima = User::factory()->create();

    $this->alvo = makeEstablishment(owner: $this->vitima);
    $this->meu = makeEstablishment(owner: $this->intruso);

    $this->categoriaAlvo = Category::factory()->create(['establishment_id' => $this->alvo->id]);
    $this->produtoAlvo = Product::factory()->forCategory($this->categoriaAlvo)->create();
    $this->pedidoAlvo = Order::factory()->create(['establishment_id' => $this->alvo->id]);
    $this->grupoAlvo = AddonGroup::factory()->create(['establishment_id' => $this->alvo->id]);
});

it('nega leitura de recursos de outro estabelecimento', function (string $route): void {
    $uri = str_replace(
        ['{establishment}', '{category}', '{product}', '{order}'],
        [(string) test()->alvo->id, (string) test()->categoriaAlvo->id, (string) test()->produtoAlvo->id, (string) test()->pedidoAlvo->id],
        $route,
    );

    $this->actingAs($this->intruso)->getJson($uri)->assertStatus(403);
})->with([
    '/api/establishments/{establishment}',
    '/api/establishments/{establishment}/dashboard',
    '/api/establishments/{establishment}/categories',
    '/api/establishments/{establishment}/categories/{category}',
    '/api/establishments/{establishment}/products',
    '/api/establishments/{establishment}/products/{product}',
    '/api/establishments/{establishment}/orders',
    '/api/establishments/{establishment}/orders/{order}',
    '/api/establishments/{establishment}/addon-groups',
    '/api/establishments/{establishment}/qrcode',
    '/api/establishments/{establishment}/subscription',
]);

it('nega escrita em recursos de outro estabelecimento', function (string $method, string $route): void {
    $uri = str_replace(
        ['{establishment}', '{category}', '{product}', '{order}'],
        [(string) test()->alvo->id, (string) test()->categoriaAlvo->id, (string) test()->produtoAlvo->id, (string) test()->pedidoAlvo->id],
        $route,
    );

    $this->actingAs($this->intruso)->json($method, $uri, ['name' => 'Invadido', 'price' => 1])
        ->assertStatus(403);
})->with([
    ['POST', '/api/establishments/{establishment}/categories'],
    ['PUT', '/api/establishments/{establishment}/categories/{category}'],
    ['DELETE', '/api/establishments/{establishment}/categories/{category}'],
    ['POST', '/api/establishments/{establishment}/products'],
    ['PUT', '/api/establishments/{establishment}/products/{product}'],
    ['DELETE', '/api/establishments/{establishment}/products/{product}'],
    ['POST', '/api/establishments/{establishment}/products/{product}/duplicate'],
    ['POST', '/api/establishments/{establishment}/products/{product}/toggle'],
    ['POST', '/api/establishments/{establishment}/categories/reorder'],
    ['POST', '/api/establishments/{establishment}/products/reorder'],
    ['PUT', '/api/establishments/{establishment}/business-hours'],
    ['POST', '/api/establishments/{establishment}/publish'],
    ['POST', '/api/establishments/{establishment}/unpublish'],
    ['POST', '/api/establishments/{establishment}/qrcode/regenerate'],
    ['DELETE', '/api/establishments/{establishment}'],
]);

it('devolve 404 ao usar um recurso alheio dentro do proprio estabelecimento', function (): void {
    // A URL aponta para o estabelecimento do intruso, mas o id do recurso é da vítima.
    $this->actingAs($this->intruso)
        ->getJson("/api/establishments/{$this->meu->id}/products/{$this->produtoAlvo->id}")
        ->assertStatus(404);

    $this->actingAs($this->intruso)
        ->putJson("/api/establishments/{$this->meu->id}/categories/{$this->categoriaAlvo->id}", ['name' => 'Invadida'])
        ->assertStatus(404);

    $this->actingAs($this->intruso)
        ->getJson("/api/establishments/{$this->meu->id}/orders/{$this->pedidoAlvo->id}")
        ->assertStatus(404);

    $this->actingAs($this->intruso)
        ->deleteJson("/api/establishments/{$this->meu->id}/addon-groups/{$this->grupoAlvo->id}")
        ->assertStatus(404);
});

it('nao vaza nada mesmo com dados alheios no corpo da requisicao', function (): void {
    // Tenta prender um produto do intruso a uma categoria da vítima.
    $this->actingAs($this->intruso)
        ->postJson("/api/establishments/{$this->meu->id}/products", [
            'name' => 'Produto Cruzado',
            'price' => 10,
            'category_id' => $this->categoriaAlvo->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');

    expect(Product::where('name', 'Produto Cruzado')->exists())->toBeFalse();
});

it('nao permite reordenar recursos alheios pelo proprio estabelecimento', function (): void {
    $ordemOriginal = $this->categoriaAlvo->sort_order;

    $this->actingAs($this->intruso)
        ->postJson("/api/establishments/{$this->meu->id}/categories/reorder", [
            'ids' => [$this->categoriaAlvo->id],
        ])
        ->assertOk();

    expect($this->categoriaAlvo->refresh()->sort_order)->toBe($ordemOriginal);
});

it('lista somente os proprios estabelecimentos', function (): void {
    $this->actingAs($this->intruso)
        ->getJson('/api/establishments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->meu->id);
});

it('nao expoe dados sensiveis do pagamento na api', function (): void {
    $payment = Payment::factory()->create([
        'establishment_id' => $this->meu->id,
        'gateway_payload' => ['secret_token' => 'abc', 'card' => '4111111111111111'],
    ]);

    $response = $this->actingAs($this->intruso)
        ->getJson(route('api.subscription.show', $this->meu))
        ->assertOk();

    $body = $response->getContent();

    expect($body)->not->toContain('4111111111111111')
        ->and($body)->not->toContain('secret_token')
        ->and($payment->fresh()->gateway_payload)->not->toBeNull();
});

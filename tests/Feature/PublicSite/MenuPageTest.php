<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MenuView;
use App\Models\Product;

beforeEach(function (): void {
    $this->establishment = makeEstablishment(attributes: [
        'slug' => 'sabor-e-ponto',
        'name' => 'Sabor & Ponto',
        'description' => 'A melhor lanchonete do bairro',
        'manual_status' => 'open',
    ]);

    $this->category = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Hambúrgueres',
    ]);

    $this->product = Product::factory()->forCategory($this->category)->create([
        'name' => 'X-Bacon',
        'price_cents' => 2990,
    ]);
});

it('renderiza o cardapio publicado', function (): void {
    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertSee('Sabor &amp; Ponto', false)
        ->assertSee('Hambúrgueres', false)
        ->assertSee('X-Bacon')
        ->assertSee('R$ 29,90');
});

it('devolve 404 para cardapio nao publicado', function (): void {
    $this->establishment->update(['is_published' => false]);

    $this->get('/cardapio/sabor-e-ponto')->assertNotFound();
});

it('devolve 404 para slug inexistente', function (): void {
    $this->get('/cardapio/nao-existe')->assertNotFound();
});

it('esconde produtos e categorias inativos', function (): void {
    $inativo = Product::factory()->forCategory($this->category)->create([
        'name' => 'Produto Fora do Ar',
        'is_active' => false,
    ]);

    $categoriaOculta = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Categoria Oculta',
        'is_active' => false,
    ]);
    Product::factory()->forCategory($categoriaOculta)->create(['name' => 'Item Escondido']);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertDontSee($inativo->name)
        ->assertDontSee('Categoria Oculta')
        ->assertDontSee('Item Escondido');
});

it('nao mostra produtos de outro estabelecimento', function (): void {
    $outro = makeEstablishment();
    Product::factory()->create([
        'establishment_id' => $outro->id,
        'name' => 'Produto do Vizinho',
    ]);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertDontSee('Produto do Vizinho');
});

it('publica as meta tags de seo', function (): void {
    $response = $this->get('/cardapio/sabor-e-ponto')->assertOk();

    $response->assertSee('<link rel="canonical" href="'.$this->establishment->publicUrl().'"', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('og:title', false)
        ->assertSee('og:type', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Restaurant"', false);
});

it('marca como noindex quando o dono desativa a indexacao', function (): void {
    $this->establishment->update(['is_indexable' => false]);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('mostra a marca da plataforma no plano free', function (): void {
    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertSee('Cardápio digital por', false);
});

it('remove a marca da plataforma no plano pro', function (): void {
    $pro = makeEstablishment(proPlan(), attributes: ['slug' => 'pizzaria-pro']);
    $categoria = Category::factory()->create(['establishment_id' => $pro->id]);
    Product::factory()->forCategory($categoria)->create();

    $this->get('/cardapio/pizzaria-pro')
        ->assertOk()
        ->assertDontSee('Cardápio digital por', false);
});

it('indica quando o estabelecimento esta fechado', function (): void {
    $this->establishment->update(['manual_status' => 'closed']);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertSee('Fechado no momento');
});

it('contabiliza a visualizacao do cardapio', function (): void {
    $this->get('/cardapio/sabor-e-ponto')->assertOk();

    expect((int) MenuView::where('establishment_id', $this->establishment->id)->sum('views'))->toBe(1);

    $this->get('/cardapio/sabor-e-ponto')->assertOk();

    expect((int) MenuView::where('establishment_id', $this->establishment->id)->sum('views'))->toBe(2);
    expect(MenuView::where('establishment_id', $this->establishment->id)->count())->toBe(1);
});

it('carrega o cardapio inteiro sem consultas n+1', function (): void {
    // Vários produtos e categorias: a contagem de queries não pode crescer com eles.
    foreach (range(1, 5) as $index) {
        $categoria = Category::factory()->create([
            'establishment_id' => $this->establishment->id,
            'name' => "Categoria {$index}",
        ]);

        Product::factory()->count(4)->forCategory($categoria)->create();
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get('/cardapio/sabor-e-ponto')->assertOk();

    // Estabelecimento + horários + categorias + produtos + grupos + adicionais
    // + plano/assinatura + registro de visualização.
    expect($queries)->toBeLessThanOrEqual(15);
});

it('exibe produtos sem categoria em uma secao propria', function (): void {
    Product::factory()->create([
        'establishment_id' => $this->establishment->id,
        'category_id' => null,
        'name' => 'Item Sem Categoria',
        'price_cents' => 1500,
    ]);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertSee('Outros')
        ->assertSee('Item Sem Categoria')
        ->assertSee('R$ 15,00');
});

it('nao exibe produto inativo sem categoria', function (): void {
    Product::factory()->inactive()->create([
        'establishment_id' => $this->establishment->id,
        'category_id' => null,
        'name' => 'Rascunho Sem Categoria',
    ]);

    $this->get('/cardapio/sabor-e-ponto')
        ->assertOk()
        ->assertDontSee('Rascunho Sem Categoria');
});

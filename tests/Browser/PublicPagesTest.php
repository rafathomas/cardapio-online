<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;

/**
 * Estas paginas passam pelo servidor de verdade, entao pegam problemas que o
 * teste de request nao ve — como um arquivo estatico em public/ sobrescrevendo
 * uma rota dinamica.
 */
it('serve o robots.txt gerado pela aplicacao', function (): void {
    $page = visit('/robots.txt');

    $page->assertSee('Disallow: /app')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap:');
});

it('serve o sitemap com os cardapios publicados', function (): void {
    $establishment = makeEstablishment(attributes: ['slug' => 'no-sitemap']);

    visit('/sitemap.xml')->assertSee($establishment->slug);
});

it('renderiza a landing page sem erros de console', function (): void {
    seedPlans();

    visit('/')
        ->assertSee('Seu cardápio online em poucos minutos.')
        ->assertSee('Como funciona')
        ->assertSee('Planos')
        ->assertSee('Perguntas frequentes')
        ->assertNoJavaScriptErrors()
        ->assertNoBrokenImages();
});

it('mantem a landing page legivel no celular', function (): void {
    seedPlans();

    $page = visit('/')->on()->iPhone15();

    $page->assertSee('Criar meu cardápio grátis')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);
});

it('nao expoe o painel a visitantes anonimos', function (): void {
    $establishment = makeEstablishment();
    Category::factory()->create(['establishment_id' => $establishment->id]);
    Product::factory()->create(['establishment_id' => $establishment->id]);

    // A SPA redireciona para o login quando não há sessão.
    visit('/app')->assertSee('Entrar')->assertDontSee('Dashboard');
});

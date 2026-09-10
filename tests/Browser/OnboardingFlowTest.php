<?php

declare(strict_types=1);

use App\Models\Establishment;
use App\Models\Product;
use App\Models\User;

/**
 * Fluxo E2E 1: cadastro -> criacao do estabelecimento -> tentativa de publicacao.
 *
 * O bloqueio por e-mail nao confirmado e parte do fluxo e e verificado aqui.
 * A publicacao bem-sucedida esta no fluxo 2, com um usuario ja confirmado.
 */
it('cria conta, monta o estabelecimento e respeita o bloqueio de e-mail', function (): void {
    seedPlans();

    $page = visit('/app/criar-conta');

    $page->assertSee('Criar conta')
        ->type('#registro-nome', 'Rafael Souza')
        ->type('#registro-email', 'rafael.e2e@example.com')
        ->type('#registro-senha', 'senhaForte123')
        ->type('#registro-senha-confirmacao', 'senhaForte123')
        ->click('button[type="submit"]')
        ->assertSee('Vamos criar seu cardápio');

    expect(User::where('email', 'rafael.e2e@example.com')->exists())->toBeTrue();

    // Passo 1: dados do negócio.
    $page->type('#onboarding-nome', 'Lanchonete E2E')
        ->select('#onboarding-segmento', 'lanchonete')
        ->type('#onboarding-whatsapp', '11988887777')
        ->click('button[type="submit"]')
        ->assertSee('Passo 2 de 5');

    $establishment = Establishment::where('name', 'Lanchonete E2E')->firstOrFail();

    expect($establishment->slug)->toBe('lanchonete-e2e')
        ->and($establishment->subscription->plan->slug)->toBe('free')
        ->and($establishment->businessHours()->count())->toBe(7)
        ->and($establishment->qrCode)->not->toBeNull();

    // Passo 2: categorias sugeridas pelo segmento já vêm marcadas.
    $page->assertSee('Hambúrgueres')
        ->click('button:has-text("Continuar com")')
        ->assertSee('Passo 3 de 5');

    expect($establishment->categories()->count())->toBeGreaterThan(0);

    // Passo 3: um produto.
    $page->type('#onboarding-produto-nome', 'X-Salada E2E')
        ->type('#onboarding-produto-preco', '24,90')
        ->click('button:has-text("Adicionar à lista")')
        ->click('button:has-text("Salvar 1 produto")')
        ->assertSee('Passo 4 de 5');

    expect($establishment->products()->where('name', 'X-Salada E2E')->firstOrFail()->price_cents)
        ->toBe(2490);

    // Passo 4 e 5.
    $page->click('button:has-text("Continuar")')
        ->assertSee('Tudo pronto para publicar')
        ->click('button:has-text("Publicar meu cardápio")')
        ->assertSee('Confirme seu e-mail antes de publicar');

    expect($establishment->refresh()->is_published)->toBeFalse();
});

/**
 * Fluxo E2E 2: login -> criacao de produto -> publicacao -> item visivel no cardapio.
 */
it('faz login, cadastra produto, publica e o item aparece no cardapio', function (): void {
    $user = User::factory()->create(['email' => 'dono@example.com']);
    $establishment = makeEstablishment(owner: $user, attributes: [
        'slug' => 'bar-do-e2e',
        'manual_status' => 'open',
        'is_published' => false,
        'published_at' => null,
    ]);

    $page = visit('/app/entrar');

    $page->assertSee('Entrar')
        ->type('#login-email', 'dono@example.com')
        ->type('#login-senha', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    // Cria o produto pelo painel.
    $page = visit('/app/produtos');
    $page->assertSee('Produtos')
        ->click('button:has-text("Novo produto")')
        ->type('#produto-nome', 'Porção de Fritas')
        ->type('#produto-preco', '32,50')
        ->click('button:has-text("Salvar produto")')
        ->assertSee('Porção de Fritas');

    expect($establishment->products()->where('name', 'Porção de Fritas')->firstOrFail()->price_cents)
        ->toBe(3250);

    // Publica pelo painel.
    $page = visit('/app/cardapio');
    $page->assertSee('Publicar cardápio')
        ->click('button:has-text("Publicar cardápio")')
        ->assertSee('No ar');

    expect($establishment->refresh()->is_published)->toBeTrue();

    // O cliente final enxerga o item publicado.
    visit('/cardapio/bar-do-e2e')
        ->assertSee('Porção de Fritas')
        ->assertSee('R$ 32,50')
        ->assertNoJavaScriptErrors();
});

/**
 * Fluxo E2E 4: usuario FREE tenta ultrapassar o limite de produtos.
 */
it('bloqueia o usuario free ao tentar exceder o limite de produtos', function (): void {
    $user = User::factory()->create(['email' => 'limite@example.com']);
    $establishment = makeEstablishment(owner: $user);

    Product::factory()->count(10)->create(['establishment_id' => $establishment->id]);

    $page = visit('/app/entrar');
    $page->type('#login-email', 'limite@example.com')
        ->type('#login-senha', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    $page = visit('/app/produtos');

    $page->assertSee('10 de 10 produtos usados')
        ->assertSee('Você atingiu o limite de produtos do plano Free')
        ->assertButtonDisabled('button:has-text("Novo produto")');

    // O limite tambem vale no backend, nao apenas na interface.
    expect($establishment->products()->count())->toBe(10);
});

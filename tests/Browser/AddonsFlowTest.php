<?php

declare(strict_types=1);

use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/**
 * Adicionais sao um recurso do plano Pro: criacao no painel e uso no cardapio.
 */
it('cria um grupo de adicionais e usa no cardapio publico', function (): void {
    $user = User::factory()->create(['email' => 'pro@example.com']);
    $establishment = makeEstablishment(proPlan(), owner: $user, attributes: [
        'slug' => 'pro-adicionais',
        'manual_status' => 'open',
    ]);

    $categoria = Category::factory()->create([
        'establishment_id' => $establishment->id,
        'name' => 'Hambúrgueres',
    ]);
    $produto = Product::factory()->forCategory($categoria)->create([
        'name' => 'X-Tudo',
        'price_cents' => 3000,
    ]);

    $page = visit('/app/entrar');
    $page->type('#login-email', 'pro@example.com')
        ->type('#login-senha', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    // Cria o grupo pelo painel.
    $page = visit('/app/adicionais');
    $page->assertSee('Adicionais')
        ->click('button:has-text("Novo grupo")')
        ->type('#addon-nome', 'Turbine seu lanche')
        ->fill('input[aria-label="Nome da opção 1"]', 'Bacon')
        ->fill('input[aria-label="Preço da opção 1"]', '5,00')
        ->click('button:has-text("Adicionar opção")')
        ->fill('input[aria-label="Nome da opção 2"]', 'Cheddar')
        ->fill('input[aria-label="Preço da opção 2"]', '4,00')
        ->assertValue('input[aria-label="Nome da opção 2"]', 'Cheddar')
        ->click('button[form="addon-form"]')
        ->assertSee('Turbine seu lanche')
        ->assertSee('Bacon');

    $grupo = AddonGroup::where('establishment_id', $establishment->id)->firstOrFail();
    expect($grupo->addons()->count())->toBe(2);

    // Vincula ao produto.
    $page = visit('/app/produtos');
    $page->assertSee('X-Tudo')
        ->click('button:has-text("Ações para X-Tudo")')
        ->click('button:has-text("Editar")')
        ->assertSee('Grupos de adicionais')
        ->check('label:has-text("Turbine seu lanche") input[type="checkbox"]')
        ->click('button:has-text("Salvar produto")');

    expect($produto->refresh()->addonGroups()->count())->toBe(1);

    // O cliente vê e escolhe o adicional.
    $page = visit('/cardapio/pro-adicionais');
    $page->click('button[data-add-product="'.$produto->id.'"]')
        ->assertSee('Turbine seu lanche')
        ->assertSee('+ R$ 5,00')
        ->click('label:has-text("Bacon")')
        ->assertSee('Adicionar · R$ 35,00');
});

it('esconde a criacao de adicionais no plano free', function (): void {
    $user = User::factory()->create(['email' => 'free@example.com']);
    makeEstablishment(owner: $user);

    $page = visit('/app/entrar');
    $page->type('#login-email', 'free@example.com')
        ->type('#login-senha', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    visit('/app/adicionais')
        ->assertSee('Adicionais fazem parte do plano Pro')
        ->assertButtonDisabled('button:has-text("Novo grupo")');
});

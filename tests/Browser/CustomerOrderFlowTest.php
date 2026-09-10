<?php

declare(strict_types=1);

use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;

/**
 * Fluxo E2E 3: cliente abre o cardapio, monta o carrinho e finaliza no WhatsApp.
 */
beforeEach(function (): void {
    $this->establishment = makeEstablishment(attributes: [
        'slug' => 'sabor-e2e',
        'name' => 'Sabor E2E',
        'whatsapp' => '11988887777',
        'manual_status' => 'open',
    ]);

    $categoria = Category::factory()->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Hambúrgueres',
    ]);

    $this->burger = Product::factory()->forCategory($categoria)->create([
        'name' => 'X-Bacon',
        'price_cents' => 2990,
    ]);

    $this->fries = Product::factory()->forCategory($categoria)->create([
        'name' => 'Batata Frita',
        'price_cents' => 1200,
    ]);
});

it('permite montar o carrinho e finalizar o pedido pelo whatsapp', function (): void {
    $page = visit('/cardapio/sabor-e2e');

    $page->assertSee('Sabor E2E')
        ->assertSee('Aberto agora')
        ->assertSee('X-Bacon')
        ->assertSee('R$ 29,90')
        ->assertNoJavaScriptErrors();

    // Adiciona dois itens ao carrinho.
    $page->click('button[data-add-product="'.$this->burger->id.'"]')
        ->click('button[data-add-product="'.$this->burger->id.'"]')
        ->click('button[data-add-product="'.$this->fries->id.'"]')
        ->assertSee('Ver pedido')
        ->assertSee('R$ 71,80');

    // Abre o carrinho: o total vem recalculado pelo servidor.
    $page->click('button:has-text("Ver pedido")')
        ->assertSee('Seu pedido')
        ->assertSee('X-Bacon')
        ->assertSee('Batata Frita')
        ->assertSee('R$ 71,80');

    // Dados do cliente.
    $page->click('button:has-text("Continuar")')
        ->assertSee('Finalizar pedido')
        ->type('#cliente-nome', 'Mariana Costa')
        ->type('#cliente-telefone', '11987654321')
        ->type('#obs-pedido', 'Sem cebola, por favor');

    $page->click('button:has-text("Enviar pelo WhatsApp")');

    // O pedido fica registrado com o total calculado no servidor.
    $order = retry(20, fn () => Order::query()->latest('id')->first() ?? throw new RuntimeException('sem pedido'), 250);

    expect($order->establishment_id)->toBe($this->establishment->id)
        ->and($order->customer_name)->toBe('Mariana Costa')
        ->and($order->total_cents)->toBe(7180)
        ->and($order->notes)->toBe('Sem cebola, por favor')
        ->and($order->items)->toHaveCount(2);

    expect($order->whatsapp_message)
        ->toContain('Olá! Gostaria de fazer um pedido:')
        ->toContain('2x X-Bacon — R$ 59,80')
        ->toContain('1x Batata Frita — R$ 12,00')
        ->toContain('*Total: R$ 71,80*')
        ->toContain('Sem cebola, por favor');
});

it('exige adicional obrigatorio antes de adicionar ao carrinho', function (): void {
    $grupo = AddonGroup::factory()->required(min: 1, max: 1)->create([
        'establishment_id' => $this->establishment->id,
        'name' => 'Ponto da carne',
    ]);
    ProductAddon::factory()->create([
        'addon_group_id' => $grupo->id,
        'name' => 'Ao ponto',
        'price_cents' => 0,
    ]);
    $this->burger->addonGroups()->attach($grupo);

    $page = visit('/cardapio/sabor-e2e');

    $page->click('button[data-add-product="'.$this->burger->id.'"]')
        ->assertSee('Ponto da carne')
        ->assertSee('Obrigatório')
        ->assertSee('Escolha: Ponto da carne');

    // Escolhendo a opção, o botão libera com o total correto.
    $page->click('label:has-text("Ao ponto")')
        ->assertSee('Adicionar · R$ 29,90');
});

it('nao permite pedir com o estabelecimento fechado', function (): void {
    $this->establishment->update(['manual_status' => 'closed']);

    $page = visit('/cardapio/sabor-e2e');

    $page->assertSee('Fechado no momento')
        ->assertSee('Estamos fechados agora')
        ->assertButtonDisabled('button[data-add-product="'.$this->burger->id.'"]');

    expect(Order::count())->toBe(0);
});

it('mantem o cardapio utilizavel em tela de celular', function (): void {
    $page = visit('/cardapio/sabor-e2e')->on()->iPhone15();

    $page->assertSee('X-Bacon')
        ->assertNoJavaScriptErrors()
        ->assertNoBrokenImages();

    // Nenhuma rolagem horizontal: o conteúdo cabe na largura da tela.
    $page->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);
});

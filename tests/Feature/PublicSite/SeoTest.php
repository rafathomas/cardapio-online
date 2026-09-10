<?php

declare(strict_types=1);

it('serve o robots.txt apontando para o sitemap', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /app')
        ->assertSee('Disallow: /admin')
        ->assertSee(route('sitemap'));
});

it('lista apenas cardapios publicados e indexaveis no sitemap', function (): void {
    $publico = makeEstablishment(attributes: ['slug' => 'aparece']);
    $rascunho = makeEstablishment(attributes: ['slug' => 'rascunho', 'is_published' => false]);
    $privado = makeEstablishment(attributes: ['slug' => 'privado', 'is_indexable' => false]);

    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee($publico->publicUrl(), false)
        ->assertDontSee($rascunho->publicUrl(), false)
        ->assertDontSee($privado->publicUrl(), false);
});

it('renderiza a landing page com dados estruturados', function (): void {
    seedPlans();

    $this->get('/')
        ->assertOk()
        ->assertSee('Seu cardápio online em poucos minutos.', false)
        ->assertSee('Crie seu cardápio, compartilhe o link e receba pedidos pelo WhatsApp.', false)
        ->assertSee('Criar meu cardápio grátis', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"SoftwareApplication"', false);
});

it('mostra os planos na landing page', function (): void {
    seedPlans();

    $this->get('/')
        ->assertOk()
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('R$ 39,90');
});

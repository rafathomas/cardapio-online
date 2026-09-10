<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->create();
});

it('bloqueia acesso de quem nao e administrador', function (string $method, string $uri): void {
    $this->actingAs($this->client)->json($method, $uri)->assertStatus(403);
})->with([
    ['GET', '/api/admin/metrics'],
    ['GET', '/api/admin/users'],
    ['GET', '/api/admin/establishments'],
    ['GET', '/api/admin/plans'],
    ['GET', '/api/admin/subscriptions'],
    ['GET', '/api/admin/payments'],
    ['GET', '/api/admin/payment-events'],
]);

it('bloqueia acesso anonimo ao painel da plataforma', function (): void {
    $this->getJson('/api/admin/metrics')->assertStatus(401);
});

it('mostra as metricas da plataforma', function (): void {
    makeEstablishment(owner: $this->client);
    makeEstablishment(proPlan());

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.metrics'))
        ->assertOk()
        ->assertJsonStructure([
            'users_total', 'users_blocked', 'establishments_total', 'establishments_published',
            'subscriptions_by_status', 'payments_approved', 'revenue_total_cents',
            'mrr_cents', 'mrr_formatted', 'orders_total',
        ]);
});

it('calcula o mrr somando apenas assinaturas ativas pagas', function (): void {
    makeEstablishment(proPlan());
    makeEstablishment();

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.metrics'))
        ->assertOk()
        ->assertJsonPath('mrr_cents', 3990);
});

it('lista e busca usuarios', function (): void {
    // Termo improvavel de colidir com os nomes aleatorios do faker pt_BR.
    User::factory()->create(['name' => 'Zoraide Xavantes', 'email' => 'zoraide.xv@example.com']);

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.users').'?search=zoraide')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'zoraide.xv@example.com');
});

it('bloqueia e desbloqueia um usuario', function (): void {
    $this->actingAs($this->admin)
        ->postJson(route('api.admin.users.block', $this->client))
        ->assertOk();

    expect($this->client->refresh()->isBlocked())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_blocked']);

    $this->actingAs($this->admin)
        ->postJson(route('api.admin.users.unblock', $this->client))
        ->assertOk();

    expect($this->client->refresh()->isBlocked())->toBeFalse();
});

it('impede o administrador de bloquear a si mesmo', function (): void {
    $this->actingAs($this->admin)
        ->postJson(route('api.admin.users.block', $this->admin))
        ->assertStatus(422);

    expect($this->admin->refresh()->isBlocked())->toBeFalse();
});

it('impede bloquear outro administrador', function (): void {
    $outro = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->postJson(route('api.admin.users.block', $outro))
        ->assertStatus(422);
});

it('barra o usuario bloqueado no painel do cliente', function (): void {
    $establishment = makeEstablishment(owner: $this->client);

    $this->actingAs($this->admin)->postJson(route('api.admin.users.block', $this->client));

    $this->actingAs($this->client->refresh())
        ->getJson(route('api.establishments.show', $establishment))
        ->assertStatus(403);
});

it('lista os estabelecimentos com o dono', function (): void {
    $establishment = makeEstablishment(owner: $this->client, attributes: ['name' => 'Bar do Zé']);

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.establishments'))
        ->assertOk()
        ->assertJsonPath('data.0.name', $establishment->name)
        ->assertJsonPath('data.0.owner.email', $this->client->email);
});

it('permite ao administrador configurar o preco do plano', function (): void {
    $pro = proPlan();

    $this->actingAs($this->admin)
        ->putJson(route('api.admin.plans.update', $pro), [
            'price' => 49.90,
            'max_products' => null,
            'features' => ['remove_branding', 'statistics'],
        ])
        ->assertOk()
        ->assertJsonPath('data.price_cents', 4990);

    expect($pro->refresh()->features)->toBe(['remove_branding', 'statistics']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'admin.plan_updated']);
});

it('recusa preco negativo na edicao de plano', function (): void {
    $this->actingAs($this->admin)
        ->putJson(route('api.admin.plans.update', proPlan()), ['price' => -10])
        ->assertStatus(422)
        ->assertJsonValidationErrors('price');
});

it('recusa recurso desconhecido na edicao de plano', function (): void {
    $this->actingAs($this->admin)
        ->putJson(route('api.admin.plans.update', proPlan()), ['features' => ['poderes-magicos']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('features.0');
});

it('cancela uma assinatura pelo painel da plataforma', function (): void {
    $establishment = makeEstablishment(proPlan(), owner: $this->client);
    $subscription = $establishment->subscription;

    $this->actingAs($this->admin)
        ->postJson(route('api.admin.subscriptions.cancel', $subscription))
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled);
    $this->assertDatabaseHas('audit_logs', ['action' => 'admin.subscription_canceled']);
});

it('lista pagamentos e eventos de webhook', function (): void {
    $establishment = makeEstablishment(owner: $this->client);
    Payment::factory()->approved()->create(['establishment_id' => $establishment->id]);

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.payments'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.payment-events'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

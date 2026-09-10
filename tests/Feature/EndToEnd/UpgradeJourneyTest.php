<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Establishment;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Jornadas 5 e 6 do plano de testes: upgrade pago e confirmacao por webhook.
 *
 * Executadas por HTTP (e nao no navegador) porque precisam simular a API do
 * Mercado Pago com Http::fake — o servidor dos testes de navegador roda em
 * outro processo e nao enxergaria o fake.
 */
beforeEach(function (): void {
    Storage::fake('public');
    seedPlans();
});

it('leva um usuario free do cadastro ao plano pro com pagamento confirmado', function (): void {
    Http::fake([
        '*/checkout/preferences' => Http::response([
            'id' => 'pref-jornada',
            'init_point' => 'https://mercadopago.com/checkout/pref-jornada',
        ], 201),
    ]);

    // 1. Cadastro.
    $this->postJson(route('api.auth.register'), [
        'name' => 'Marina Lopes',
        'email' => 'marina@example.com',
        'password' => 'senhaForte123',
        'password_confirmation' => 'senhaForte123',
    ])->assertCreated();

    $user = User::where('email', 'marina@example.com')->firstOrFail();

    // 2. Cria o estabelecimento — nasce no plano Free.
    $establishment = $this->postJson(route('api.establishments.store'), [
        'name' => 'Pizzaria da Marina',
        'segment' => 'pizzaria',
        'whatsapp' => '11988887777',
    ])->assertCreated()->json('data');

    $establishmentId = $establishment['id'];

    expect(Establishment::find($establishmentId)->subscription->plan->slug)->toBe('free');

    // 3. Esbarra no limite do Free.
    Product::factory()->count(10)->create(['establishment_id' => $establishmentId]);

    $this->postJson("/api/establishments/{$establishmentId}/products", [
        'name' => 'Produto 11',
        'price' => '20,00',
    ])->assertStatus(422)->assertJsonPath('error_code', 'plan_limit_products');

    // Recursos PRO também estão bloqueados.
    $this->putJson("/api/establishments/{$establishmentId}", [
        'name' => 'Pizzaria da Marina',
        'segment' => 'pizzaria',
        'whatsapp' => '11988887777',
        'primary_color' => '#16A34A',
    ])->assertStatus(403)->assertJsonPath('error_code', 'plan_feature_unavailable');

    // 4. Inicia o upgrade.
    $pro = proPlan();

    $checkout = $this->postJson("/api/establishments/{$establishmentId}/subscription/checkout", [
        'plan_id' => $pro->id,
    ])->assertCreated();

    $payment = Payment::firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe($pro->price_cents)
        ->and($checkout->json('checkout_url'))->toBe('https://mercadopago.com/checkout/pref-jornada');

    // Enquanto o pagamento não é confirmado, o limite do Free continua valendo.
    expect(Establishment::find($establishmentId)->subscription->status)
        ->toBe(SubscriptionStatus::Pending);

    $this->postJson("/api/establishments/{$establishmentId}/products", [
        'name' => 'Produto 11',
        'price' => '20,00',
    ])->assertStatus(422);

    // 5. O Mercado Pago confirma o pagamento por webhook.
    Http::fake([
        '*/v1/payments/900001' => Http::response(
            mercadoPagoPaymentPayload('900001', 'approved', 39.90, $payment->external_reference),
        ),
    ]);

    $this->postJson(route('webhooks.mercadopago'), [
        'id' => 'notificacao-jornada',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => '900001'],
    ], mercadoPagoHeaders('900001'))->assertOk();

    // 6. A assinatura fica ativa no plano PRO.
    $subscription = Establishment::find($establishmentId)->subscription()->with('plan')->first();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($payment->approved_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan->slug)->toBe('pro')
        ->and($subscription->current_period_end)->not->toBeNull();

    // 7. Os limites caem e os recursos PRO liberam.
    $this->actingAs($user->refresh())
        ->postJson("/api/establishments/{$establishmentId}/products", [
            'name' => 'Produto 11',
            'price' => '20,00',
        ])->assertCreated();

    $this->putJson("/api/establishments/{$establishmentId}", [
        'name' => 'Pizzaria da Marina',
        'segment' => 'pizzaria',
        'whatsapp' => '11988887777',
        'primary_color' => '#16A34A',
    ])->assertOk();

    // 8. A marca da plataforma some do cardápio público.
    $establishmentModel = Establishment::find($establishmentId);
    $establishmentModel->forceFill(['is_published' => true])->save();

    $this->get($establishmentModel->publicUrl())
        ->assertOk()
        ->assertDontSee('Cardápio digital por', false);
});

it('webhook duplicado nao concede periodo extra na jornada', function (): void {
    Http::fake([
        '*/checkout/preferences' => Http::response([
            'id' => 'pref-dup',
            'init_point' => 'https://mp/checkout',
        ], 201),
    ]);

    $user = User::factory()->create();
    $establishment = makeEstablishment(owner: $user);
    $pro = proPlan();

    $this->actingAs($user)
        ->postJson("/api/establishments/{$establishment->id}/subscription/checkout", ['plan_id' => $pro->id])
        ->assertCreated();

    $payment = Payment::firstOrFail();

    Http::fake([
        '*/v1/payments/900002' => Http::response(
            mercadoPagoPaymentPayload('900002', 'approved', 39.90, $payment->external_reference),
        ),
    ]);

    $body = [
        'id' => 'notificacao-repetida',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => '900002'],
    ];

    $this->postJson(route('webhooks.mercadopago'), $body, mercadoPagoHeaders('900002'))->assertOk();

    $periodoInicial = $establishment->subscription()->first()->current_period_end;

    // Dez reentregas da mesma notificação.
    foreach (range(1, 10) as $ignored) {
        $this->postJson(route('webhooks.mercadopago'), $body, mercadoPagoHeaders('900002'))
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');
    }

    expect(PaymentEvent::count())->toBe(1)
        ->and(Payment::count())->toBe(1)
        ->and($establishment->subscription()->first()->current_period_end->timestamp)
        ->toBe($periodoInicial->timestamp);
});

it('pagamento recusado mantem o estabelecimento no plano free', function (): void {
    Http::fake([
        '*/checkout/preferences' => Http::response(['id' => 'pref-r', 'init_point' => 'https://mp/c'], 201),
    ]);

    $user = User::factory()->create();
    $establishment = makeEstablishment(owner: $user);
    $pro = proPlan();

    $this->actingAs($user)
        ->postJson("/api/establishments/{$establishment->id}/subscription/checkout", ['plan_id' => $pro->id])
        ->assertCreated();

    $payment = Payment::firstOrFail();

    Http::fake([
        '*/v1/payments/900003' => Http::response(
            mercadoPagoPaymentPayload('900003', 'rejected', 39.90, $payment->external_reference),
        ),
    ]);

    $this->postJson(route('webhooks.mercadopago'), [
        'id' => 'notificacao-recusada',
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => '900003'],
    ], mercadoPagoHeaders('900003'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);

    // Continua limitado ao plano Free.
    Product::factory()->count(10)->create(['establishment_id' => $establishment->id]);

    $this->actingAs($user)
        ->postJson("/api/establishments/{$establishment->id}/products", ['name' => 'Extra', 'price' => 10])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'plan_limit_products');
});

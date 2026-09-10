<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->establishment = makeEstablishment(owner: $this->user);
    $this->pro = proPlan();

    Http::fake([
        '*/checkout/preferences' => Http::response([
            'id' => 'pref-abc',
            'init_point' => 'https://mercadopago.com/checkout/pref-abc',
        ], 201),
    ]);
});

it('lista os planos publicamente', function (): void {
    $this->getJson(route('api.plans.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'free')
        ->assertJsonPath('data.1.slug', 'pro');
});

it('mostra a assinatura atual', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.subscription.show', $this->establishment))
        ->assertOk()
        ->assertJsonPath('data.plan.slug', 'free')
        ->assertJsonPath('data.status', 'active');
});

it('cria o checkout e devolve a url do gateway', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $this->establishment), [
            'plan_id' => $this->pro->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('checkout_url', 'https://mercadopago.com/checkout/pref-abc')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount_cents', 3990);

    // O checkout apenas inicia a cobranca: nada de plano ativo ainda.
    expect($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('nunca aceita o valor enviado pelo cliente', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $this->establishment), [
            'plan_id' => $this->pro->id,
            'amount_cents' => 1,
            'price' => 0.01,
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount_cents', 3990);

    expect(Payment::first()->amount_cents)->toBe(3990);
});

it('recusa checkout de plano inexistente', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $this->establishment), ['plan_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plan_id');
});

it('recusa checkout do plano gratuito', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $this->establishment), [
            'plan_id' => freePlan()->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'plan_not_payable');
});

it('impede iniciar checkout em estabelecimento de outro usuario', function (): void {
    $alheio = makeEstablishment();

    $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $alheio), ['plan_id' => $this->pro->id])
        ->assertStatus(403);

    expect(Payment::count())->toBe(0);
});

it('reconsulta o gateway em vez de confiar no retorno do navegador', function (): void {
    $payment = Payment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'subscription_id' => $this->establishment->subscription->id,
        'plan_id' => $this->pro->id,
        'gateway_payment_id' => '777001',
        'amount_cents' => $this->pro->price_cents,
        'status' => PaymentStatus::Pending,
    ]);

    Http::fake([
        '*/v1/payments/777001' => Http::response(
            mercadoPagoPaymentPayload('777001', 'approved', 39.90, $payment->external_reference),
        ),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('api.payments.sync', [$this->establishment, $payment]))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('subscription.status', 'active');
});

it('nao ativa o plano se o gateway ainda nao aprovou', function (): void {
    // Fluxo real: checkout coloca a assinatura em "pending".
    $this->actingAs($this->user)
        ->postJson(route('api.subscription.checkout', $this->establishment), ['plan_id' => $this->pro->id])
        ->assertCreated();

    $payment = Payment::firstOrFail();

    Http::fake([
        '*/v1/payments/777002' => Http::response(
            mercadoPagoPaymentPayload('777002', 'pending', 39.90, $payment->external_reference),
        ),
    ]);

    $payment->update(['gateway_payment_id' => '777002']);

    $this->actingAs($this->user)
        ->postJson(route('api.payments.sync', [$this->establishment, $payment]))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('subscription.status', 'pending');

    expect($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('impede sincronizar pagamento de outro estabelecimento', function (): void {
    $alheio = makeEstablishment();
    $payment = Payment::factory()->create(['establishment_id' => $alheio->id]);

    $this->actingAs($this->user)
        ->postJson(route('api.payments.sync', [$this->establishment, $payment]))
        ->assertStatus(404);
});

it('cancela a assinatura mantendo o acesso ate o fim do periodo', function (): void {
    $establishment = makeEstablishment($this->pro, owner: $this->user);

    $this->actingAs($this->user)
        ->postJson(route('api.subscription.cancel', $establishment))
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    expect($establishment->subscription()->first()->canceled_at)->not->toBeNull();
});

it('impede cancelar assinatura de outro usuario', function (): void {
    $alheio = makeEstablishment($this->pro);

    $this->actingAs($this->user)
        ->postJson(route('api.subscription.cancel', $alheio))
        ->assertStatus(403);

    expect($alheio->subscription()->first()->status)->toBe(SubscriptionStatus::Active);
});

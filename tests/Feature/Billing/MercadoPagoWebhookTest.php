<?php

declare(strict_types=1);

use App\Enums\PaymentEventStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentEvent;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->pro = proPlan();
    $this->establishment = makeEstablishment();

    Http::fake([
        '*/checkout/preferences' => Http::response([
            'id' => 'pref-1',
            'init_point' => 'https://mercadopago.com/checkout/pref-1',
        ], 201),
    ]);

    $this->payment = app(SubscriptionManager::class)
        ->startCheckout($this->establishment, $this->pro);
});

/** Simula a resposta da API de pagamentos para o id notificado. */
function fakeGatewayPayment(string $paymentId, string $status, float $amount, string $externalReference): void
{
    Http::fake([
        "*/v1/payments/{$paymentId}" => Http::response(
            mercadoPagoPaymentPayload($paymentId, $status, $amount, $externalReference),
        ),
        '*/checkout/preferences' => Http::response(['id' => 'pref-1', 'init_point' => 'https://mp/x'], 201),
    ]);
}

function webhookBody(string $paymentId, string $notificationId = 'notif-1'): array
{
    return [
        'id' => $notificationId,
        'type' => 'payment',
        'action' => 'payment.updated',
        'data' => ['id' => $paymentId],
    ];
}

it('aprova o pagamento e ativa a assinatura', function (): void {
    fakeGatewayPayment('555001', 'approved', 39.90, $this->payment->external_reference);

    $response = $this->postJson(
        route('webhooks.mercadopago'),
        webhookBody('555001'),
        mercadoPagoHeaders('555001'),
    );

    $response->assertOk()->assertJsonPath('status', 'accepted');

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($this->payment->gateway_payment_id)->toBe('555001')
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Active);

    expect(PaymentEvent::where('gateway_resource_id', '555001')->first()->status)
        ->toBe(PaymentEventStatus::Processed);
});

it('e idempotente: dez entregas do mesmo evento nao duplicam nada', function (): void {
    fakeGatewayPayment('555002', 'approved', 39.90, $this->payment->external_reference);

    foreach (range(1, 10) as $ignored) {
        $this->postJson(
            route('webhooks.mercadopago'),
            webhookBody('555002', 'notif-repetida'),
            mercadoPagoHeaders('555002'),
        )->assertOk();
    }

    $subscription = $this->establishment->subscription()->first();

    expect(PaymentEvent::count())->toBe(1)
        ->and($this->establishment->payments()->count())->toBe(1)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);

    // O periodo foi calculado uma unica vez.
    expect($subscription->current_period_end->diffInDays(now()))->toBeLessThan(32);
});

it('marca entregas repetidas como duplicadas', function (): void {
    fakeGatewayPayment('555003', 'approved', 39.90, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555003', 'n-1'), mercadoPagoHeaders('555003'))
        ->assertOk()->assertJsonPath('status', 'accepted');

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555003', 'n-1'), mercadoPagoHeaders('555003'))
        ->assertOk()->assertJsonPath('status', 'duplicate');
});

it('processa a evolucao pendente para aprovado em notificacoes distintas', function (): void {
    // Http::fake acumula stubs e o primeiro vence, entao a evolucao de status
    // precisa vir de uma sequencia no mesmo stub.
    Http::fake([
        '*/v1/payments/555004' => Http::sequence()
            ->push(mercadoPagoPaymentPayload('555004', 'pending', 39.90, $this->payment->external_reference))
            ->push(mercadoPagoPaymentPayload('555004', 'approved', 39.90, $this->payment->external_reference)),
    ]);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555004', 'n-1'), mercadoPagoHeaders('555004'))
        ->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555004', 'n-2'), mercadoPagoHeaders('555004'))
        ->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Active);
});

it('rejeita assinatura invalida', function (): void {
    fakeGatewayPayment('555005', 'approved', 39.90, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555005'), [
        'x-signature' => 'ts='.time().',v1=hash-invalido',
        'x-request-id' => 'req-1',
    ])->assertStatus(401);

    expect(PaymentEvent::count())->toBe(0)
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejeita webhook sem cabecalho de assinatura', function (): void {
    $this->postJson(route('webhooks.mercadopago'), webhookBody('555006'))
        ->assertStatus(401);
});

it('rejeita assinatura fora da janela de tolerancia', function (): void {
    fakeGatewayPayment('555007', 'approved', 39.90, $this->payment->external_reference);

    $this->postJson(
        route('webhooks.mercadopago'),
        webhookBody('555007'),
        mercadoPagoHeaders('555007', timestamp: time() - 4000),
    )->assertStatus(401);
});

it('rejeita notificacao malformada', function (array $body): void {
    $this->postJson(route('webhooks.mercadopago'), $body, mercadoPagoHeaders('x'))
        ->assertStatus(422);
})->with([
    'sem tipo' => [['data' => ['id' => '1']]],
    'pagamento sem id' => [[['type' => 'payment']][0]],
]);

it('ignora tipos de notificacao que nao sao pagamento', function (): void {
    $this->postJson(route('webhooks.mercadopago'), [
        'id' => 'n-merchant',
        'type' => 'merchant_order',
        'data' => ['id' => '123'],
    ], mercadoPagoHeaders('123'))
        ->assertOk()
        ->assertJsonPath('status', 'ignored');

    expect(PaymentEvent::count())->toBe(0);
});

it('ignora pagamento que nao pertence a aplicacao', function (): void {
    fakeGatewayPayment('999999', 'approved', 10.00, 'referencia-desconhecida');

    $this->postJson(route('webhooks.mercadopago'), webhookBody('999999'), mercadoPagoHeaders('999999'))
        ->assertOk();

    expect(PaymentEvent::first()->status)->toBe(PaymentEventStatus::Ignored)
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('nao ativa assinatura quando o valor confirmado diverge', function (): void {
    // O gateway confirma 1 real para um plano de 39,90.
    fakeGatewayPayment('555008', 'approved', 1.00, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555008'), mercadoPagoHeaders('555008'))
        ->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->payment->status_detail)->toBe('amount_mismatch')
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('registra pagamento recusado sem ativar a assinatura', function (): void {
    fakeGatewayPayment('555009', 'rejected', 39.90, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555009'), mercadoPagoHeaders('555009'))
        ->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('registra pagamento cancelado', function (): void {
    fakeGatewayPayment('555010', 'cancelled', 39.90, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555010'), mercadoPagoHeaders('555010'))
        ->assertOk();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Canceled)
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Canceled);
});

it('localiza o pagamento pela referencia externa quando o id ainda nao foi gravado', function (): void {
    expect($this->payment->gateway_payment_id)->toBeNull();

    fakeGatewayPayment('555011', 'approved', 39.90, $this->payment->external_reference);

    $this->postJson(route('webhooks.mercadopago'), webhookBody('555011'), mercadoPagoHeaders('555011'))
        ->assertOk();

    expect($this->payment->refresh()->gateway_payment_id)->toBe('555011')
        ->and($this->payment->status)->toBe(PaymentStatus::Approved);
});

it('nao exige csrf no endpoint de webhook', function (): void {
    fakeGatewayPayment('555012', 'approved', 39.90, $this->payment->external_reference);

    $this->post(route('webhooks.mercadopago'), webhookBody('555012'), mercadoPagoHeaders('555012'))
        ->assertOk();
});

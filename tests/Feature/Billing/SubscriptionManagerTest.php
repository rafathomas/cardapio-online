<?php

declare(strict_types=1);

use App\DTOs\GatewayPaymentData;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentException;
use App\Services\Billing\SubscriptionManager;
use App\Support\Money;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->manager = app(SubscriptionManager::class);
    $this->pro = proPlan();
    $this->establishment = makeEstablishment();
});

function fakePreference(): void
{
    Http::fake([
        '*/checkout/preferences' => Http::response([
            'id' => 'pref-123',
            'init_point' => 'https://mercadopago.com/checkout/pref-123',
            'external_reference' => null,
        ], 201),
    ]);
}

function approvedResult(int $cents, string $paymentId = '111222333'): GatewayPaymentData
{
    return new GatewayPaymentData(
        status: PaymentStatus::Approved,
        amount: Money::fromCents($cents),
        gatewayPaymentId: $paymentId,
        approvedAt: now()->toImmutable(),
        raw: ['id' => $paymentId, 'status' => 'approved'],
    );
}

it('cria um pagamento pendente e deixa a assinatura aguardando', function (): void {
    fakePreference();

    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe($this->pro->price_cents)
        ->and($payment->checkout_url)->toBe('https://mercadopago.com/checkout/pref-123')
        ->and($payment->external_reference)->not->toBeEmpty()
        ->and($payment->idempotency_key)->not->toBeEmpty();

    $subscription = $this->establishment->subscription()->first();

    // Criar o checkout NAO ativa nada.
    expect($subscription->status)->toBe(SubscriptionStatus::Pending);
});

it('envia a chave de idempotencia ao gateway', function (): void {
    fakePreference();

    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    Http::assertSent(fn ($request) => $request->hasHeader('X-Idempotency-Key', $payment->idempotency_key));
});

it('nunca envia o preco vindo de fora: usa o cadastrado no plano', function (): void {
    fakePreference();

    $this->manager->startCheckout($this->establishment, $this->pro);

    Http::assertSent(function ($request) {
        return $request['items'][0]['unit_price'] === $this->pro->price()->toDecimal();
    });
});

it('recusa checkout de plano gratuito', function (): void {
    $this->manager->startCheckout($this->establishment, freePlan());
})->throws(PaymentException::class);

it('ativa a assinatura quando o pagamento e aprovado', function (): void {
    fakePreference();
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $this->manager->applyGatewayResult($payment, approvedResult($this->pro->price_cents));

    $subscription = $this->establishment->subscription()->first();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_id)->toBe($this->pro->id)
        ->and($subscription->current_period_end)->not->toBeNull()
        ->and($subscription->grantsAccess())->toBeTrue()
        ->and($payment->refresh()->approved_at)->not->toBeNull();
});

it('nao estende o periodo ao reprocessar o mesmo estado', function (): void {
    fakePreference();
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $result = approvedResult($this->pro->price_cents);

    $this->manager->applyGatewayResult($payment, $result);
    $firstEnd = $this->establishment->subscription()->first()->current_period_end;

    // Dez reprocessamentos do mesmo evento.
    foreach (range(1, 10) as $ignored) {
        $this->manager->applyGatewayResult($payment->refresh(), $result);
    }

    expect($this->establishment->subscription()->first()->current_period_end->timestamp)
        ->toBe($firstEnd->timestamp);
});

it('recusa ativar quando o valor confirmado diverge do cobrado', function (): void {
    fakePreference();
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    // O gateway informou 1 centavo em vez do preco do plano.
    $this->manager->applyGatewayResult($payment, approvedResult(1));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->status_detail)->toBe('amount_mismatch')
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('mantem a assinatura pendente quando o pagamento e recusado', function (): void {
    fakePreference();
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $this->manager->applyGatewayResult($payment, new GatewayPaymentData(
        status: PaymentStatus::Rejected,
        amount: Money::fromCents($this->pro->price_cents),
        gatewayPaymentId: '999',
    ));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Pending);
});

it('move assinatura ativa para atraso quando a renovacao e recusada', function (): void {
    $establishment = makeEstablishment($this->pro);
    $payment = $establishment->payments()->create([
        'subscription_id' => $establishment->subscription->id,
        'plan_id' => $this->pro->id,
        'gateway' => 'mercadopago',
        'external_reference' => 'ref-renew',
        'idempotency_key' => 'key-renew',
        'status' => PaymentStatus::Pending,
        'amount_cents' => $this->pro->price_cents,
    ]);

    $this->manager->applyGatewayResult($payment, new GatewayPaymentData(
        status: PaymentStatus::Rejected,
        amount: Money::fromCents($this->pro->price_cents),
    ));

    $subscription = $establishment->subscription()->first();

    // O acesso continua ate o fim do periodo ja pago.
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->grantsAccess())->toBeTrue();
});

it('cancela a assinatura em caso de estorno', function (): void {
    fakePreference();
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);
    $this->manager->applyGatewayResult($payment, approvedResult($this->pro->price_cents));

    $this->manager->applyGatewayResult($payment->refresh(), new GatewayPaymentData(
        status: PaymentStatus::Refunded,
        amount: Money::fromCents($this->pro->price_cents),
        gatewayPaymentId: '111222333',
    ));

    expect($this->establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Canceled);
});

it('cancela mantendo o acesso ate o fim do periodo', function (): void {
    $establishment = makeEstablishment($this->pro);
    $subscription = $establishment->subscription;
    $periodEnd = $subscription->current_period_end;

    $this->manager->cancel($subscription);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->ends_at->timestamp)->toBe($periodEnd->timestamp)
        ->and($subscription->canceled_at)->not->toBeNull();
});

it('expira assinaturas com periodo vencido', function (): void {
    $establishment = makeEstablishment($this->pro);
    $establishment->subscription->update([
        'current_period_end' => now()->subDay(),
    ]);

    $expired = $this->manager->expireOverdue();

    expect($expired)->toBe(1)
        ->and($establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Expired);
});

it('renova somando ao periodo vigente em vez de reiniciar', function (): void {
    $establishment = makeEstablishment($this->pro);
    $subscription = $establishment->subscription;
    $originalEnd = $subscription->current_period_end->copy();

    $this->manager->activate($subscription);

    expect($subscription->refresh()->current_period_end->timestamp)
        ->toBeGreaterThan($originalEnd->timestamp);
});

it('recusa novo checkout para o mesmo plano ja ativo', function (): void {
    $establishment = makeEstablishment($this->pro);

    $this->manager->startCheckout($establishment, $this->pro);
})->throws(PaymentException::class);

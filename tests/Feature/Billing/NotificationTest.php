<?php

declare(strict_types=1);

use App\DTOs\GatewayPaymentData;
use App\Enums\PaymentStatus;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Services\Billing\SubscriptionManager;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();

    Http::fake([
        '*/checkout/preferences' => Http::response(['id' => 'p', 'init_point' => 'https://mp/c'], 201),
    ]);

    $this->pro = proPlan();
    $this->establishment = makeEstablishment();
    $this->manager = app(SubscriptionManager::class);
});

it('avisa o dono quando o pagamento e aprovado', function (): void {
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $this->manager->applyGatewayResult($payment, new GatewayPaymentData(
        status: PaymentStatus::Approved,
        amount: Money::fromCents($this->pro->price_cents),
        gatewayPaymentId: '1',
    ));

    Notification::assertSentTo($this->establishment->user, PaymentApprovedNotification::class);
});

it('avisa o dono quando o pagamento e recusado', function (): void {
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $this->manager->applyGatewayResult($payment, new GatewayPaymentData(
        status: PaymentStatus::Rejected,
        amount: Money::fromCents($this->pro->price_cents),
        gatewayPaymentId: '2',
    ));

    Notification::assertSentTo($this->establishment->user, PaymentFailedNotification::class);
});

it('nao envia notificacao duplicada ao reprocessar o mesmo estado', function (): void {
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $result = new GatewayPaymentData(
        status: PaymentStatus::Approved,
        amount: Money::fromCents($this->pro->price_cents),
        gatewayPaymentId: '3',
    );

    foreach (range(1, 5) as $ignored) {
        $this->manager->applyGatewayResult($payment->refresh(), $result);
    }

    Notification::assertSentToTimes($this->establishment->user, PaymentApprovedNotification::class, 1);
});

it('nao notifica quando o valor confirmado diverge', function (): void {
    $payment = $this->manager->startCheckout($this->establishment, $this->pro);

    $this->manager->applyGatewayResult($payment, new GatewayPaymentData(
        status: PaymentStatus::Approved,
        amount: Money::fromCents(1),
        gatewayPaymentId: '4',
    ));

    Notification::assertNothingSent();
});

<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;

it('define quais estados concedem acesso', function (): void {
    expect(SubscriptionStatus::Trial->grantsAccess())->toBeTrue()
        ->and(SubscriptionStatus::Active->grantsAccess())->toBeTrue()
        // Em atraso o acesso continua ate o fim do periodo pago.
        ->and(SubscriptionStatus::PastDue->grantsAccess())->toBeTrue()
        ->and(SubscriptionStatus::Pending->grantsAccess())->toBeFalse()
        ->and(SubscriptionStatus::Canceled->grantsAccess())->toBeFalse()
        ->and(SubscriptionStatus::Expired->grantsAccess())->toBeFalse();
});

it('permite as transicoes validas', function (SubscriptionStatus $from, SubscriptionStatus $to): void {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [SubscriptionStatus::Pending, SubscriptionStatus::Active],
    [SubscriptionStatus::Trial, SubscriptionStatus::Active],
    [SubscriptionStatus::Active, SubscriptionStatus::PastDue],
    [SubscriptionStatus::PastDue, SubscriptionStatus::Active],
    [SubscriptionStatus::Active, SubscriptionStatus::Canceled],
    [SubscriptionStatus::Canceled, SubscriptionStatus::Active],
    [SubscriptionStatus::Expired, SubscriptionStatus::Pending],
]);

it('bloqueia transicoes sem sentido', function (): void {
    expect(SubscriptionStatus::Canceled->canTransitionTo(SubscriptionStatus::PastDue))->toBeFalse()
        ->and(SubscriptionStatus::Expired->canTransitionTo(SubscriptionStatus::Trial))->toBeFalse();
});

it('sempre aceita permanecer no mesmo estado', function (SubscriptionStatus $status): void {
    expect($status->canTransitionTo($status))->toBeTrue();
})->with(SubscriptionStatus::cases());

it('traduz os status do mercado pago', function (?string $raw, PaymentStatus $expected): void {
    expect(PaymentStatus::fromMercadoPago($raw))->toBe($expected);
})->with([
    ['approved', PaymentStatus::Approved],
    ['pending', PaymentStatus::Pending],
    ['in_process', PaymentStatus::InProcess],
    ['authorized', PaymentStatus::InProcess],
    ['rejected', PaymentStatus::Rejected],
    ['cancelled', PaymentStatus::Canceled],
    ['refunded', PaymentStatus::Refunded],
    ['charged_back', PaymentStatus::ChargedBack],
    // Qualquer coisa desconhecida cai em pendente: nunca libera acesso por engano.
    ['status_que_nao_existe', PaymentStatus::Pending],
    [null, PaymentStatus::Pending],
]);

it('considera apenas aprovado como sucesso', function (): void {
    expect(PaymentStatus::Approved->isSuccessful())->toBeTrue()
        ->and(PaymentStatus::InProcess->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::Pending->isSuccessful())->toBeFalse();
});

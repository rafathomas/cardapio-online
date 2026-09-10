<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

it('promove e rebaixa um administrador', function (): void {
    $user = User::factory()->create(['email' => 'novo.admin@example.com']);

    $this->artisan('cardapio:make-admin', ['email' => 'novo.admin@example.com'])->assertSuccessful();

    expect($user->refresh()->isAdmin())->toBeTrue();

    $this->artisan('cardapio:make-admin', ['email' => 'novo.admin@example.com', '--revoke' => true])
        ->assertSuccessful();

    expect($user->refresh()->isAdmin())->toBeFalse();
});

it('falha ao promover usuario inexistente', function (): void {
    $this->artisan('cardapio:make-admin', ['email' => 'ninguem@example.com'])->assertFailed();
});

it('expira assinaturas com periodo vencido', function (): void {
    $vencida = makeEstablishment(proPlan());
    $vencida->subscription->update(['current_period_end' => now()->subDay()]);

    $vigente = makeEstablishment(proPlan());

    $this->artisan('cardapio:expire-subscriptions')->assertSuccessful();

    expect($vencida->subscription()->first()->status)->toBe(SubscriptionStatus::Expired)
        ->and($vigente->subscription()->first()->status)->toBe(SubscriptionStatus::Active);
});

it('reconcilia pagamentos pendentes com o gateway', function (): void {
    $pro = proPlan();
    $establishment = makeEstablishment();

    $payment = Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'subscription_id' => $establishment->subscription->id,
        'plan_id' => $pro->id,
        'gateway_payment_id' => '880001',
        'amount_cents' => $pro->price_cents,
        'status' => PaymentStatus::Pending,
    ]);

    Http::fake([
        '*/v1/payments/880001' => Http::response(
            mercadoPagoPaymentPayload('880001', 'approved', 39.90, $payment->external_reference),
        ),
    ]);

    $this->artisan('cardapio:sync-payments')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($establishment->subscription()->first()->status)->toBe(SubscriptionStatus::Active);
});

it('ignora pagamentos fora da janela de reconciliacao', function (): void {
    $establishment = makeEstablishment();

    $antigo = Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'gateway_payment_id' => '880002',
        'status' => PaymentStatus::Pending,
        'created_at' => now()->subDays(10),
    ]);

    Http::fake();

    $this->artisan('cardapio:sync-payments', ['--hours' => 24])->assertSuccessful();

    Http::assertNothingSent();
    expect($antigo->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('registra as tarefas agendadas', function (): void {
    $comandos = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command)
        ->implode(' ');

    expect($comandos)
        ->toContain('cardapio:expire-subscriptions')
        ->toContain('cardapio:sync-payments');
});

<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Establishment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Playwright\Playwright;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

// Testes de navegador (Playwright) exercitam a aplicacao servida de verdade.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
 * O servidor de testes atende a aplicacao no mesmo processo do Pest, entao
 * telas que disparam varias chamadas encostam no timeout padrao de 5s.
 */
if (class_exists(Playwright::class)) {
    Playwright::setTimeout(20_000);
}

/*
|--------------------------------------------------------------------------
| Helpers de dominio
|--------------------------------------------------------------------------
*/

/** Garante os planos FREE e PRO no banco de teste. */
function seedPlans(): void
{
    (new PlanSeeder)->run();
}

function freePlan(): Plan
{
    seedPlans();

    return Plan::where('slug', 'free')->firstOrFail();
}

function proPlan(): Plan
{
    seedPlans();

    return Plan::where('slug', 'pro')->firstOrFail();
}

/**
 * Cria um estabelecimento completo com dono e assinatura.
 * Sem plano informado, usa o FREE.
 */
function makeEstablishment(?Plan $plan = null, array $attributes = [], ?User $owner = null): Establishment
{
    $plan ??= freePlan();
    $owner ??= User::factory()->create();

    $establishment = Establishment::factory()
        ->for($owner)
        ->create($attributes);

    $establishment->subscriptions()->create([
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now(),
        'current_period_end' => $plan->isFree() ? null : now()->addMonth(),
    ]);

    return $establishment->refresh();
}

/**
 * Cabecalhos de webhook do Mercado Pago com assinatura HMAC valida.
 *
 * @return array<string, string>
 */
function mercadoPagoHeaders(string $dataId, string $requestId = 'req-test-1', ?int $timestamp = null): array
{
    $timestamp ??= time();
    $secret = (string) config('mercadopago.webhook_secret');

    $manifest = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $requestId, $timestamp);
    $hash = hash_hmac('sha256', $manifest, $secret);

    return [
        'x-signature' => "ts={$timestamp},v1={$hash}",
        'x-request-id' => $requestId,
    ];
}

/** Resposta minima da API de pagamentos do Mercado Pago. */
function mercadoPagoPaymentPayload(
    string $id,
    string $status,
    float $amount,
    string $externalReference,
    array $extra = [],
): array {
    return [
        'id' => (int) $id,
        'status' => $status,
        'status_detail' => 'accredited',
        'transaction_amount' => $amount,
        'currency_id' => 'BRL',
        'external_reference' => $externalReference,
        'payment_method_id' => 'pix',
        'payment_type_id' => 'bank_transfer',
        'date_approved' => $status === 'approved' ? now()->toIso8601String() : null,
        ...$extra,
    ];
}

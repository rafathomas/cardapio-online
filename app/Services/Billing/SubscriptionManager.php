<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\DTOs\GatewayPaymentData;
use App\DTOs\PaymentIntentData;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Events\PaymentApproved;
use App\Events\PaymentFailed;
use App\Events\SubscriptionStatusChanged;
use App\Exceptions\PaymentException;
use App\Models\Establishment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Maquina de estados da assinatura.
 *
 * Regra central: o estado financeiro NUNCA vem do frontend. Uma assinatura so
 * se torna "active" quando um pagamento confirmado pelo gateway transita para
 * "approved" e o valor confere com o plano cobrado.
 */
class SubscriptionManager
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Assinatura inicial do plano gratuito, criada junto com o estabelecimento.
     */
    public function startFreePlan(Establishment $establishment, Plan $plan): Subscription
    {
        $now = CarbonImmutable::now();

        return $establishment->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $now,
            'current_period_end' => null,
            'trial_ends_at' => $plan->trial_days > 0 ? $now->addDays($plan->trial_days) : null,
        ]);
    }

    /**
     * Inicia a cobranca de um plano pago e devolve o pagamento criado.
     * A assinatura fica "pending" ate a confirmacao do gateway.
     */
    public function startCheckout(
        Establishment $establishment,
        Plan $plan,
        string $method = 'checkout',
    ): Payment {
        if ($plan->isFree()) {
            throw PaymentException::planNotPayable();
        }

        $current = $establishment->subscription()->with('plan')->first();

        if ($current && $current->plan_id === $plan->id && $current->status === SubscriptionStatus::Active
            && ($current->current_period_end === null || $current->current_period_end->isFuture())) {
            throw PaymentException::alreadySubscribed();
        }

        $externalReference = 'sub_'.$establishment->id.'_'.Str::lower(Str::random(20));
        $idempotencyKey = (string) Str::uuid();

        $subscription = $this->pendingSubscriptionFor($establishment, $plan);

        $intent = new PaymentIntentData(
            amount: $plan->price(),
            description: "Assinatura {$plan->name} — {$establishment->name}",
            externalReference: $externalReference,
            idempotencyKey: $idempotencyKey,
            payerEmail: $establishment->user->email,
            payerName: $establishment->user->name,
            metadata: [
                'establishment_id' => $establishment->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
            ],
        );

        $result = $method === 'pix'
            ? $this->gateway->createPixPayment($intent)
            : $this->gateway->createCheckout($intent);

        $payment = Payment::create([
            'establishment_id' => $establishment->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'gateway' => $this->gateway->name(),
            'gateway_payment_id' => $result->gatewayPaymentId,
            'external_reference' => $externalReference,
            'idempotency_key' => $idempotencyKey,
            'status' => $result->status,
            'status_detail' => $result->statusDetail,
            'amount_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'payment_method' => $result->paymentMethod ?? $method,
            'payment_type' => $result->paymentType,
            'checkout_url' => $result->checkoutUrl,
            'qr_code_payload' => $result->qrCodePayload,
            'gateway_payload' => $result->raw,
        ]);

        $this->audit->log('payment.created', $payment, $establishment->user, $establishment, [
            'plan' => $plan->slug,
            'amount_cents' => $plan->price_cents,
            'method' => $method,
        ]);

        // Um Pix pode ja nascer aprovado em ambientes de teste.
        if ($result->status === PaymentStatus::Approved) {
            $this->applyGatewayResult($payment, $result);
        }

        return $payment->refresh();
    }

    /**
     * Reconcilia um pagamento local com o estado real informado pelo gateway.
     * Idempotente: reprocessar o mesmo estado nao duplica efeitos.
     */
    public function applyGatewayResult(Payment $payment, GatewayPaymentData $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $previousStatus = $payment->status;

            if ($result->gatewayPaymentId !== null) {
                $payment->gateway_payment_id = $result->gatewayPaymentId;
            }

            $payment->status_detail = $result->statusDetail;
            $payment->payment_method = $result->paymentMethod ?? $payment->payment_method;
            $payment->payment_type = $result->paymentType ?? $payment->payment_type;
            $payment->qr_code_payload = $result->qrCodePayload ?? $payment->qr_code_payload;
            $payment->gateway_payload = $result->raw ?: $payment->gateway_payload;

            $amountMatches = $result->amount->cents === $payment->amount_cents;

            if ($result->status === PaymentStatus::Approved && ! $amountMatches) {
                // Valor divergente nunca libera assinatura.
                $payment->status_detail = 'amount_mismatch';
                $payment->save();

                Log::critical('payment.amount_mismatch', [
                    'payment_id' => $payment->id,
                    'expected_cents' => $payment->amount_cents,
                    'received_cents' => $result->amount->cents,
                ]);

                $this->audit->log('payment.amount_mismatch', $payment, null, $payment->establishment, [
                    'expected_cents' => $payment->amount_cents,
                    'received_cents' => $result->amount->cents,
                ]);

                return $payment;
            }

            $payment->status = $result->status;

            if ($result->status === PaymentStatus::Approved && $payment->approved_at === null) {
                $payment->approved_at = $result->approvedAt ?? CarbonImmutable::now();
            }

            $payment->save();

            // Efeitos colaterais so acontecem na TRANSICAO de estado.
            if ($previousStatus === $result->status) {
                return $payment;
            }

            $this->reflectOnSubscription($payment, $result->status);

            return $payment;
        });
    }

    private function reflectOnSubscription(Payment $payment, PaymentStatus $status): void
    {
        $subscription = $payment->subscription;

        if (! $subscription) {
            return;
        }

        match ($status) {
            PaymentStatus::Approved => $this->activate($subscription, $payment),
            PaymentStatus::Rejected, PaymentStatus::Canceled => $this->handleFailure($subscription, $payment, $status),
            PaymentStatus::Refunded, PaymentStatus::ChargedBack => $this->transition($subscription, SubscriptionStatus::Canceled),
            default => null,
        };
    }

    public function activate(Subscription $subscription, ?Payment $payment = null): Subscription
    {
        $now = CarbonImmutable::now();
        $plan = $subscription->plan;

        // Renovacao soma ao periodo vigente; primeira ativacao parte de agora.
        $start = $subscription->current_period_end !== null && $subscription->current_period_end->isFuture()
            ? CarbonImmutable::parse($subscription->current_period_end)
            : $now;

        $subscription->forceFill([
            'plan_id' => $payment?->plan_id ?? $subscription->plan_id,
            'current_period_start' => $subscription->current_period_start ?? $now,
            'current_period_end' => $plan->billing_period->addTo($start),
            'canceled_at' => null,
            'ends_at' => null,
            'gateway' => $payment?->gateway ?? $subscription->gateway,
        ]);

        $this->transition($subscription, SubscriptionStatus::Active);

        if ($payment) {
            event(new PaymentApproved($payment->fresh(['establishment', 'plan'])));
        }

        return $subscription->refresh();
    }

    private function handleFailure(Subscription $subscription, Payment $payment, PaymentStatus $status): void
    {
        // Uma assinatura vigente nao cai por causa de uma tentativa recusada:
        // ela vai para past_due e mantem o acesso ate o fim do periodo pago.
        $target = match (true) {
            $subscription->status === SubscriptionStatus::Active => SubscriptionStatus::PastDue,
            $status === PaymentStatus::Canceled => SubscriptionStatus::Canceled,
            default => SubscriptionStatus::Pending,
        };

        $this->transition($subscription, $target);

        event(new PaymentFailed($payment->fresh(['establishment', 'plan']), $status));
    }

    /**
     * Aplica uma transicao respeitando o grafo de estados.
     * Transicoes invalidas sao registradas e ignoradas em vez de corromper o estado.
     */
    public function transition(Subscription $subscription, SubscriptionStatus $target): Subscription
    {
        $current = $subscription->status;

        if ($current === $target) {
            $subscription->save();

            return $subscription;
        }

        if (! $current->canTransitionTo($target)) {
            Log::warning('subscription.invalid_transition', [
                'subscription_id' => $subscription->id,
                'from' => $current->value,
                'to' => $target->value,
            ]);

            $subscription->save();

            return $subscription;
        }

        $subscription->status = $target;

        if ($target === SubscriptionStatus::Canceled && $subscription->canceled_at === null) {
            $subscription->canceled_at = CarbonImmutable::now();
        }

        $subscription->save();

        $this->audit->log('subscription.status_changed', $subscription, null, $subscription->establishment, [
            'from' => $current->value,
            'to' => $target->value,
        ]);

        event(new SubscriptionStatusChanged($subscription, $current, $target));

        return $subscription;
    }

    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        if ($immediately) {
            $subscription->ends_at = CarbonImmutable::now();
            $subscription->current_period_end = CarbonImmutable::now();
        } else {
            $subscription->ends_at = $subscription->current_period_end;
        }

        return $this->transition($subscription, SubscriptionStatus::Canceled);
    }

    /** Marca como expiradas as assinaturas cujo periodo terminou. */
    public function expireOverdue(): int
    {
        $expired = 0;

        Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::Trial->value,
            ])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', CarbonImmutable::now())
            ->with('establishment')
            ->chunkById(100, function ($subscriptions) use (&$expired): void {
                foreach ($subscriptions as $subscription) {
                    $this->transition($subscription, SubscriptionStatus::Expired);
                    $expired++;
                }
            });

        return $expired;
    }

    private function pendingSubscriptionFor(Establishment $establishment, Plan $plan): Subscription
    {
        $subscription = $establishment->subscription()->first();

        if ($subscription === null) {
            return $establishment->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Pending,
                'gateway' => $this->gateway->name(),
            ]);
        }

        // Assinatura ativa em outro plano permanece valida ate a confirmacao do upgrade.
        if ($subscription->grantsAccess() && ! $subscription->plan->isFree()) {
            return $subscription;
        }

        $subscription->plan_id = $plan->id;
        $subscription->gateway = $this->gateway->name();
        $this->transition($subscription, SubscriptionStatus::Pending);

        return $subscription->refresh();
    }

    public function amountFor(Plan $plan): Money
    {
        return $plan->price();
    }
}

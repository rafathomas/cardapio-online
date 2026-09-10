<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\DTOs\WebhookSignatureData;
use App\Enums\PaymentEventStatus;
use App\Jobs\ProcessMercadoPagoWebhook;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a comunicacao com o Mercado Pago no nivel da aplicacao.
 *
 * Responsabilidades:
 *  - validar e registrar notificacoes de forma idempotente;
 *  - reconciliar o pagamento local com o estado real consultado na API.
 *
 * O que chega no webhook nunca e usado como verdade: o corpo da notificacao
 * serve apenas para descobrir QUAL pagamento consultar.
 */
class MercadoPagoService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    /**
     * Etapas 1 a 7 do fluxo de webhook: validar, autenticar, deduplicar e registrar.
     */
    public function receiveWebhook(array $payload, array $query, array $headers): WebhookResult
    {
        $type = $this->resolveType($payload, $query);
        $resourceId = $this->resolveResourceId($payload, $query);

        if ($type === null) {
            return WebhookResult::malformed('Notificação sem tipo identificável.');
        }

        // O SaaS so reage a notificacoes de pagamento.
        if ($type !== 'payment') {
            Log::info('mercadopago.webhook.ignored', ['type' => $type]);

            return WebhookResult::ignored("Tipo não tratado: {$type}");
        }

        if ($resourceId === null || $resourceId === '') {
            return WebhookResult::malformed('Notificação de pagamento sem identificador.');
        }

        $signature = new WebhookSignatureData(
            signatureHeader: $headers['x-signature'] ?? null,
            requestId: $headers['x-request-id'] ?? null,
            resourceId: $resourceId,
        );

        if (! $this->gateway->verifyWebhookSignature($signature)) {
            Log::warning('mercadopago.webhook.invalid_signature', ['resource_id' => $resourceId]);

            return WebhookResult::invalidSignature();
        }

        $eventKey = $this->eventKey($payload, $type, $resourceId);

        $existing = PaymentEvent::query()->where('event_key', $eventKey)->first();

        if ($existing !== null) {
            Log::info('mercadopago.webhook.duplicate', ['event_key' => $eventKey]);

            return WebhookResult::duplicate($existing);
        }

        try {
            $event = PaymentEvent::create([
                'gateway' => $this->gateway->name(),
                'event_key' => $eventKey,
                'event_type' => $type,
                'event_action' => $payload['action'] ?? null,
                'gateway_resource_id' => $resourceId,
                'status' => PaymentEventStatus::Received,
                'payload' => $this->sanitizePayload($payload),
            ]);
        } catch (QueryException $e) {
            // Corrida entre duas entregas simultaneas da mesma notificacao.
            $event = PaymentEvent::query()->where('event_key', $eventKey)->first();

            if ($event === null) {
                throw $e;
            }

            return WebhookResult::duplicate($event);
        }

        ProcessMercadoPagoWebhook::dispatch($event->id);

        return WebhookResult::accepted($event);
    }

    /**
     * Etapas 8 e 9: consulta a API, atualiza o pagamento e reflete na assinatura.
     */
    public function processEvent(PaymentEvent $event): void
    {
        if ($event->status === PaymentEventStatus::Processed) {
            return;
        }

        $resourceId = (string) $event->gateway_resource_id;

        try {
            $result = $this->gateway->fetchPayment($resourceId);
        } catch (\Throwable $e) {
            $event->update([
                'status' => PaymentEventStatus::Failed,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }

        if ($result === null) {
            $event->update([
                'status' => PaymentEventStatus::Ignored,
                'error_message' => 'Pagamento não encontrado no gateway.',
                'processed_at' => CarbonImmutable::now(),
            ]);

            return;
        }

        $payment = $this->locatePayment($result->gatewayPaymentId, $result->externalReference);

        if ($payment === null) {
            Log::warning('mercadopago.webhook.unknown_payment', [
                'gateway_payment_id' => $result->gatewayPaymentId,
                'external_reference' => $result->externalReference,
            ]);

            $event->update([
                'status' => PaymentEventStatus::Ignored,
                'error_message' => 'Pagamento não pertence a esta aplicação.',
                'processed_at' => CarbonImmutable::now(),
            ]);

            return;
        }

        $payment = $this->subscriptions->applyGatewayResult($payment, $result);

        $event->update([
            'payment_id' => $payment->id,
            'status' => PaymentEventStatus::Processed,
            'processed_at' => CarbonImmutable::now(),
        ]);

        Log::info('mercadopago.webhook.processed', [
            'event_id' => $event->id,
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
        ]);
    }

    /** Reconciliacao manual/agendada de um pagamento pendente. */
    public function syncPayment(Payment $payment): Payment
    {
        if ($payment->gateway_payment_id === null) {
            return $payment;
        }

        $result = $this->gateway->fetchPayment($payment->gateway_payment_id);

        if ($result === null) {
            return $payment;
        }

        return $this->subscriptions->applyGatewayResult($payment, $result);
    }

    private function locatePayment(?string $gatewayPaymentId, ?string $externalReference): ?Payment
    {
        $query = Payment::query()->where('gateway', $this->gateway->name());

        if ($gatewayPaymentId !== null) {
            $found = (clone $query)->where('gateway_payment_id', $gatewayPaymentId)->first();

            if ($found !== null) {
                return $found;
            }
        }

        if ($externalReference !== null && $externalReference !== '') {
            return $query->where('external_reference', $externalReference)->first();
        }

        return null;
    }

    private function resolveType(array $payload, array $query): ?string
    {
        $type = $payload['type'] ?? $payload['topic'] ?? $query['type'] ?? $query['topic'] ?? null;

        return is_string($type) && $type !== '' ? strtolower($type) : null;
    }

    private function resolveResourceId(array $payload, array $query): ?string
    {
        $candidates = [
            $payload['data']['id'] ?? null,
            $query['data.id'] ?? null,
            $query['data']['id'] ?? null,
            $query['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * Chave determinística do evento.
     *
     * Usa o id da notificacao quando disponivel: reentregas do Mercado Pago
     * repetem esse id, enquanto uma mudanca real de status gera um id novo.
     * Sem ele, cai em um hash do conteudo relevante.
     */
    private function eventKey(array $payload, string $type, string $resourceId): string
    {
        $notificationId = $payload['id'] ?? null;

        if (is_scalar($notificationId) && (string) $notificationId !== '') {
            return "mercadopago:notification:{$notificationId}";
        }

        $fingerprint = hash('sha256', json_encode([
            $type,
            $payload['action'] ?? null,
            $resourceId,
            $payload['date_created'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return "mercadopago:{$type}:{$resourceId}:{$fingerprint}";
    }

    /** Remove chaves potencialmente sensiveis antes de persistir o payload. */
    private function sanitizePayload(array $payload): array
    {
        unset($payload['user_id'], $payload['api_version']);

        return $payload;
    }
}

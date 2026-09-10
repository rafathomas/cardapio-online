<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\DTOs\GatewayPaymentData;
use App\DTOs\PaymentIntentData;
use App\DTOs\WebhookSignatureData;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integracao HTTP com o Mercado Pago.
 *
 * Usa o cliente HTTP do Laravel em vez do SDK oficial: a superficie usada e
 * pequena, o controle sobre timeout/retry/idempotencia fica explicito e os
 * testes podem usar Http::fake sem mockar classes de terceiros.
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'mercadopago';
    }

    public function createCheckout(PaymentIntentData $intent): GatewayPaymentData
    {
        $payload = [
            'items' => [[
                'id' => $intent->externalReference,
                'title' => $intent->description,
                'quantity' => 1,
                'currency_id' => $intent->amount->currency,
                'unit_price' => $intent->amount->toDecimal(),
            ]],
            'payer' => array_filter([
                'email' => $intent->payerEmail,
                'name' => $intent->payerName,
            ]),
            'external_reference' => $intent->externalReference,
            'statement_descriptor' => config('mercadopago.statement_descriptor'),
            'metadata' => $intent->metadata,
        ];

        $notificationUrl = $intent->notificationUrl ?? config('mercadopago.notification_url');

        if ($notificationUrl) {
            $payload['notification_url'] = $notificationUrl;
        }

        $backUrls = $intent->backUrls ?: array_filter((array) config('mercadopago.back_urls'));

        if ($backUrls !== []) {
            $payload['back_urls'] = $backUrls;
            $payload['auto_return'] = 'approved';
        }

        $response = $this->request($intent->idempotencyKey)->post('/checkout/preferences', $payload);

        $data = $this->decode($response, 'criar preferência de pagamento');

        return new GatewayPaymentData(
            status: PaymentStatus::Pending,
            amount: $intent->amount,
            preferenceId: isset($data['id']) ? (string) $data['id'] : null,
            externalReference: $data['external_reference'] ?? $intent->externalReference,
            checkoutUrl: $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
            raw: $data,
        );
    }

    public function createPixPayment(PaymentIntentData $intent): GatewayPaymentData
    {
        $payload = array_filter([
            'transaction_amount' => $intent->amount->toDecimal(),
            'description' => $intent->description,
            'payment_method_id' => 'pix',
            'external_reference' => $intent->externalReference,
            'notification_url' => $intent->notificationUrl ?? config('mercadopago.notification_url'),
            'metadata' => $intent->metadata ?: null,
            'payer' => array_filter([
                'email' => $intent->payerEmail,
                'first_name' => $intent->payerName,
            ]),
        ], static fn ($value) => $value !== null && $value !== []);

        $response = $this->request($intent->idempotencyKey)->post('/v1/payments', $payload);

        $data = $this->decode($response, 'criar pagamento Pix');

        return $this->toGatewayPayment($data, $intent->amount);
    }

    public function fetchPayment(string $gatewayPaymentId): ?GatewayPaymentData
    {
        try {
            $response = $this->request()->get("/v1/payments/{$gatewayPaymentId}");
        } catch (ConnectionException $e) {
            throw PaymentException::gatewayUnavailable($e->getMessage());
        }

        if ($response->status() === 404) {
            return null;
        }

        $data = $this->decode($response, 'consultar pagamento');

        return $this->toGatewayPayment($data);
    }

    /**
     * Valida o cabecalho x-signature (HMAC-SHA256).
     *
     * Manifesto: id:<data.id>;request-id:<x-request-id>;ts:<ts>;
     */
    public function verifyWebhookSignature(WebhookSignatureData $signature): bool
    {
        if (! config('mercadopago.verify_signature')) {
            return true;
        }

        $secret = (string) config('mercadopago.webhook_secret');

        if ($secret === '') {
            Log::warning('mercadopago.webhook.secret_missing');

            return false;
        }

        $parsed = $this->parseSignatureHeader($signature->signatureHeader);

        if ($parsed === null) {
            return false;
        }

        [$timestamp, $hash] = $parsed;

        $tolerance = (int) config('mercadopago.signature_tolerance', 600);

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            Log::warning('mercadopago.webhook.signature_expired', ['ts' => $timestamp]);

            return false;
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            strtolower((string) $signature->resourceId),
            (string) $signature->requestId,
            $timestamp,
        );

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    /** @return array{0: int, 1: string}|null */
    private function parseSignatureHeader(?string $header): ?array
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $timestamp = null;
        $hash = null;

        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', trim($part), 2);

            if (count($pieces) !== 2) {
                continue;
            }

            [$key, $value] = [trim($pieces[0]), trim($pieces[1])];

            if ($key === 'ts') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $hash = $value;
            }
        }

        if ($timestamp === null || $hash === null || $hash === '') {
            return null;
        }

        return [$timestamp, $hash];
    }

    private function toGatewayPayment(array $data, ?Money $fallbackAmount = null): GatewayPaymentData
    {
        $amount = isset($data['transaction_amount'])
            ? Money::fromDecimal((float) $data['transaction_amount'], $data['currency_id'] ?? 'BRL')
            : ($fallbackAmount ?? Money::zero());

        $pix = $data['point_of_interaction']['transaction_data'] ?? [];

        return new GatewayPaymentData(
            status: PaymentStatus::fromMercadoPago($data['status'] ?? null),
            amount: $amount,
            gatewayPaymentId: isset($data['id']) ? (string) $data['id'] : null,
            externalReference: isset($data['external_reference']) ? (string) $data['external_reference'] : null,
            statusDetail: $data['status_detail'] ?? null,
            checkoutUrl: $pix['ticket_url'] ?? null,
            qrCodePayload: $pix['qr_code'] ?? null,
            paymentMethod: $data['payment_method_id'] ?? null,
            paymentType: $data['payment_type_id'] ?? null,
            approvedAt: isset($data['date_approved']) && $data['date_approved']
                ? CarbonImmutable::parse($data['date_approved'])
                : null,
            raw: $data,
        );
    }

    private function request(?string $idempotencyKey = null): PendingRequest
    {
        $token = (string) config('mercadopago.access_token');

        if ($token === '') {
            throw PaymentException::gatewayUnavailable('MERCADOPAGO_ACCESS_TOKEN não configurado.');
        }

        $request = Http::baseUrl((string) config('mercadopago.base_url'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('mercadopago.timeout', 15))
            ->retry(2, 250, throw: false);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        return $request;
    }

    private function decode(Response $response, string $operation): array
    {
        if ($response->failed()) {
            Log::error('mercadopago.request_failed', [
                'operation' => $operation,
                'status' => $response->status(),
                // Mensagem do provedor, sem credenciais.
                'error' => $response->json('message') ?? $response->json('error'),
            ]);

            throw PaymentException::gatewayUnavailable("Falha ao {$operation}.");
        }

        return (array) $response->json();
    }
}

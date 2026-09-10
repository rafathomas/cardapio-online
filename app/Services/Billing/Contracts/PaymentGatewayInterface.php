<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\DTOs\GatewayPaymentData;
use App\DTOs\PaymentIntentData;
use App\DTOs\WebhookSignatureData;
use App\Exceptions\PaymentException;

/**
 * Contrato de gateway de pagamento.
 *
 * Trocar o Mercado Pago por outro provedor exige apenas uma nova
 * implementacao registrada em PaymentServiceProvider.
 */
interface PaymentGatewayInterface
{
    /** Identificador persistido na coluna payments.gateway. */
    public function name(): string;

    /** Cria um checkout hospedado (cartao, pix, boleto). @throws PaymentException */
    public function createCheckout(PaymentIntentData $intent): GatewayPaymentData;

    /** Cria uma cobranca Pix direta com QR Code. @throws PaymentException */
    public function createPixPayment(PaymentIntentData $intent): GatewayPaymentData;

    /**
     * Consulta o estado real do pagamento no provedor.
     * E a unica fonte confiavel para liberar uma assinatura.
     */
    public function fetchPayment(string $gatewayPaymentId): ?GatewayPaymentData;

    /** Valida a autenticidade da notificacao recebida. */
    public function verifyWebhookSignature(WebhookSignatureData $signature): bool;
}

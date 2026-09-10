<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckoutRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Establishment;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\Billing\MercadoPagoService;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly MercadoPagoService $mercadoPago,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $subscription = $establishment->subscription()->with('plan')->first();

        return response()->json([
            'data' => $subscription ? new SubscriptionResource($subscription) : null,
            'payments' => PaymentResource::collection(
                $establishment->payments()->with('plan')->latest()->limit(10)->get()
            ),
        ]);
    }

    /**
     * Inicia a cobranca. Devolve a URL de checkout do gateway.
     *
     * O sucesso desta chamada NAO significa pagamento aprovado: a assinatura
     * so muda quando o webhook confirmar.
     */
    public function checkout(CheckoutRequest $request, Establishment $establishment): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $establishment->loadMissing('user');

        $payment = $this->subscriptions->startCheckout(
            $establishment,
            $plan,
            $request->validated('method', 'checkout'),
        );

        return response()->json([
            'data' => new PaymentResource($payment->load('plan')),
            'checkout_url' => $payment->checkout_url,
            'message' => 'Checkout criado. Conclua o pagamento para ativar o plano.',
        ], 201);
    }

    /** Reconsulta o gateway. Usado no retorno do checkout, sem confiar no frontend. */
    public function syncPayment(Request $request, Establishment $establishment, Payment $payment): JsonResponse
    {
        $this->authorize('view', $establishment);
        abort_unless($payment->establishment_id === $establishment->id, 404);

        $payment = $this->mercadoPago->syncPayment($payment);

        return response()->json([
            'data' => new PaymentResource($payment->load('plan')),
            'subscription' => new SubscriptionResource(
                $establishment->subscription()->with('plan')->first()
            ),
        ]);
    }

    public function cancel(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('update', $establishment);

        $subscription = $establishment->subscription()->with('plan')->firstOrFail();

        $this->authorize('cancel', $subscription);

        $this->subscriptions->cancel($subscription);

        $this->audit->log('subscription.canceled_by_user', $subscription, $request->user(), $establishment);

        return response()->json([
            'data' => new SubscriptionResource($subscription->refresh()->load('plan')),
            'message' => 'Assinatura cancelada. O acesso continua até o fim do período pago.',
        ]);
    }
}

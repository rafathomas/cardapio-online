<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint de notificacoes do Mercado Pago.
 *
 * Mantido deliberadamente fino: validacao, idempotencia e reconciliacao
 * ficam no MercadoPagoService. Responde rapido para nao gerar reentregas.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoService $service): JsonResponse
    {
        // Notificacoes atuais chegam em JSON; as antigas, em form-urlencoded.
        $payload = $request->json()->all() ?: $request->all();

        Log::info('mercadopago.webhook.received', [
            'type' => $payload['type'] ?? $payload['topic'] ?? null,
            'action' => $payload['action'] ?? null,
            'resource_id' => $payload['data']['id'] ?? $request->query('data.id'),
        ]);

        $result = $service->receiveWebhook(
            payload: $payload,
            query: $request->query(),
            headers: [
                'x-signature' => $request->header('x-signature'),
                'x-request-id' => $request->header('x-request-id'),
            ],
        );

        return response()->json([
            'status' => $result->outcome,
            'message' => $result->reason,
        ], $result->status);
    }
}

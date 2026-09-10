<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\DTOs\CartItemData;
use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\CartPreviewRequest;
use App\Http\Requests\PublicSite\StoreOrderRequest;
use App\Models\Establishment;
use App\Services\Menu\CartCalculator;
use App\Services\Menu\OrderService;
use Illuminate\Http\JsonResponse;

/**
 * Finalizacao do pedido pelo consumidor. Sem login.
 *
 * O total gravado e o total recalculado aqui — nunca o enviado pelo navegador.
 */
class PublicOrderController extends Controller
{
    public function __construct(
        private readonly CartCalculator $cart,
        private readonly OrderService $orders,
    ) {}

    /** Recalcula o carrinho para exibir o total confiavel antes de fechar. */
    public function preview(CartPreviewRequest $request, string $slug): JsonResponse
    {
        $establishment = $this->resolve($slug);

        $summary = $this->cart->calculate(
            $establishment,
            CartItemData::collection($request->validated('items')),
        );

        return response()->json(['data' => $summary->toArray()]);
    }

    public function store(StoreOrderRequest $request, string $slug): JsonResponse
    {
        $establishment = $this->resolve($slug);

        if (! $establishment->isOpen()) {
            throw CartException::establishmentClosed();
        }

        $summary = $this->cart->calculate(
            $establishment,
            CartItemData::collection($request->validated('items')),
        );

        $result = $this->orders->place(
            $establishment,
            $summary,
            $request->validated('customer'),
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'code' => $result['order']->code,
                'total_cents' => $result['order']->total_cents,
                'total_formatted' => $result['order']->total()->format(),
                'message' => $result['message'],
            ],
            'whatsapp_url' => $result['whatsapp_url'],
        ], 201);
    }

    private function resolve(string $slug): Establishment
    {
        return Establishment::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('businessHours')
            ->firstOrFail();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Establishment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $orders = $establishment->orders()
            ->with('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate(perPage: min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Establishment $establishment, Order $order): JsonResponse
    {
        $this->authorize('view', $establishment);
        abort_unless($order->establishment_id === $establishment->id, 404);

        return response()->json(['data' => new OrderResource($order->load('items'))]);
    }

    public function updateStatus(Request $request, Establishment $establishment, Order $order): JsonResponse
    {
        $this->authorize('update', $establishment);
        abort_unless($order->establishment_id === $establishment->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $order->update(['status' => $data['status']]);

        return response()->json([
            'data' => new OrderResource($order->refresh()->load('items')),
            'message' => 'Status atualizado.',
        ]);
    }
}

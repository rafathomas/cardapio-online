<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionManager;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => PlanResource::collection(Plan::query()->orderBy('sort_order')->get()),
        ]);
    }

    /** Preco e limites do plano sao configuraveis pelo administrador. */
    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999.99'],
            'max_products' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_categories' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_establishments' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', Rule::in(PlanFeature::values())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('price', $data)) {
            $data['price_cents'] = Money::fromDecimal($data['price'])->cents;
            unset($data['price']);
        }

        $plan->update($data);

        $this->audit->log('admin.plan_updated', $plan, $request->user(), meta: ['fields' => array_keys($data)]);

        return response()->json([
            'data' => new PlanResource($plan->refresh()),
            'message' => 'Plano atualizado.',
        ]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with(['plan', 'establishment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($subscriptions->items())->map(fn (Subscription $s) => [
                ...(new SubscriptionResource($s))->toArray($request),
                'establishment' => ['id' => $s->establishment->id, 'name' => $s->establishment->name, 'slug' => $s->establishment->slug],
            ]),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function cancelSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptions->cancel($subscription, immediately: $request->boolean('immediately'));

        $this->audit->log('admin.subscription_canceled', $subscription, $request->user(), meta: [
            'immediately' => $request->boolean('immediately'),
        ]);

        return response()->json([
            'data' => new SubscriptionResource($subscription->refresh()->load('plan')),
            'message' => 'Assinatura cancelada.',
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['plan', 'establishment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($payments->items())->map(fn (Payment $p) => [
                ...(new PaymentResource($p))->toArray($request),
                'establishment' => ['id' => $p->establishment->id, 'name' => $p->establishment->name],
            ]),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function paymentEvents(Request $request): JsonResponse
    {
        $events = PaymentEvent::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 25), 100));

        return response()->json([
            'data' => collect($events->items())->map(fn (PaymentEvent $e) => [
                'id' => $e->id,
                'gateway' => $e->gateway,
                'event_key' => $e->event_key,
                'event_type' => $e->event_type,
                'event_action' => $e->event_action,
                'gateway_resource_id' => $e->gateway_resource_id,
                'status' => $e->status->value,
                'error_message' => $e->error_message,
                'payment_id' => $e->payment_id,
                'processed_at' => $e->processed_at?->toIso8601String(),
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function metrics(): JsonResponse
    {
        $mrrCents = (int) Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_cents');

        return response()->json([
            'users_total' => User::query()->count(),
            'users_blocked' => User::query()->whereNotNull('blocked_at')->count(),
            'establishments_total' => Establishment::query()->count(),
            'establishments_published' => Establishment::query()->where('is_published', true)->count(),
            'subscriptions_by_status' => Subscription::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'payments_approved' => Payment::query()->where('status', PaymentStatus::Approved->value)->count(),
            'revenue_total_cents' => (int) Payment::query()
                ->where('status', PaymentStatus::Approved->value)
                ->sum('amount_cents'),
            'mrr_cents' => $mrrCents,
            'mrr_formatted' => Money::fromCents($mrrCents)->format(),
            'orders_total' => Order::query()->count(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Resources\EstablishmentResource;
use App\Http\Resources\OrderResource;
use App\Models\Establishment;
use App\Services\Plans\PlanGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(private readonly PlanGate $plans) {}

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        $establishment->load(['subscription.plan', 'businessHours'])
            ->loadCount(['products', 'categories']);

        $since = Carbon::now()->subDays(30)->startOfDay();

        $orderStats = $establishment->orders()
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(total_cents), 0) as revenue_cents')
            ->where('created_at', '>=', $since)
            ->first();

        $views = (int) $establishment->menuViews()
            ->where('viewed_on', '>=', $since->toDateString())
            ->sum('views');

        $hasStats = $this->plans->hasFeature($establishment, PlanFeature::Statistics);

        return response()->json([
            'establishment' => new EstablishmentResource($establishment),
            'plan' => $this->plans->summary($establishment),
            'metrics' => [
                'products_total' => $establishment->products_count,
                'products_active' => $establishment->products()->where('is_active', true)->count(),
                'categories_total' => $establishment->categories_count,
                'orders_last_30_days' => (int) ($orderStats->total ?? 0),
                'orders_pending' => $establishment->orders()
                    ->whereIn('status', [OrderStatus::Received->value, OrderStatus::Preparing->value])
                    ->count(),
                'views_last_30_days' => $views,
                // Faturamento e uma estatistica: so aparece em planos que a incluem.
                'revenue_last_30_days_cents' => $hasStats ? (int) ($orderStats->revenue_cents ?? 0) : null,
            ],
            'recent_orders' => OrderResource::collection(
                $establishment->orders()->with('items')->latest()->limit(5)->get()
            ),
            'checklist' => $this->onboardingChecklist($establishment),
        ]);
    }

    /** Passos do onboarding, usados para guiar o usuario novo. */
    private function onboardingChecklist(Establishment $establishment): array
    {
        $hasCategories = $establishment->categories_count > 0;
        $hasProducts = $establishment->products_count > 0;
        $hasBranding = $establishment->logo_path !== null;

        return [
            ['key' => 'business', 'label' => 'Seu negócio', 'done' => true],
            ['key' => 'categories', 'label' => 'Categorias', 'done' => $hasCategories],
            ['key' => 'products', 'label' => 'Produtos', 'done' => $hasProducts],
            ['key' => 'customization', 'label' => 'Personalização', 'done' => $hasBranding],
            ['key' => 'publish', 'label' => 'Publicar', 'done' => $establishment->is_published],
        ];
    }
}

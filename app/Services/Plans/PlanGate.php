<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Enums\PlanFeature;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Establishment;
use App\Models\Plan;
use App\Models\User;
use RuntimeException;

/**
 * Ponto unico de decisao sobre limites e recursos de plano.
 *
 * Toda checagem acontece no backend. O frontend pode esconder botoes, mas
 * a regra que vale e esta.
 */
class PlanGate
{
    /**
     * Plano efetivo do estabelecimento.
     * Sem assinatura valida, o estabelecimento cai no plano padrao (FREE).
     */
    public function planFor(Establishment $establishment): Plan
    {
        $subscription = $establishment->relationLoaded('subscription')
            ? $establishment->subscription
            : $establishment->subscription()->with('plan')->first();

        if ($subscription && $subscription->grantsAccess() && $subscription->plan) {
            return $subscription->plan;
        }

        return $this->defaultPlan();
    }

    public function defaultPlan(): Plan
    {
        $plan = Plan::query()->where('is_default', true)->where('is_active', true)->first()
            ?? Plan::query()->where('is_active', true)->orderBy('price_cents')->first();

        if (! $plan) {
            throw new RuntimeException('Nenhum plano padrão configurado. Rode os seeders.');
        }

        return $plan;
    }

    public function remainingProducts(Establishment $establishment): ?int
    {
        $plan = $this->planFor($establishment);

        if ($plan->allowsUnlimitedProducts()) {
            return null;
        }

        return max(0, $plan->max_products - $establishment->products()->count());
    }

    public function remainingCategories(Establishment $establishment): ?int
    {
        $plan = $this->planFor($establishment);

        if ($plan->allowsUnlimitedCategories()) {
            return null;
        }

        return max(0, $plan->max_categories - $establishment->categories()->count());
    }

    /** @throws PlanLimitExceededException */
    public function assertCanCreateProduct(Establishment $establishment, int $adding = 1): void
    {
        $plan = $this->planFor($establishment);

        if ($plan->allowsUnlimitedProducts()) {
            return;
        }

        if (($establishment->products()->count() + $adding) > $plan->max_products) {
            throw PlanLimitExceededException::products($plan->max_products, $plan->name);
        }
    }

    /** @throws PlanLimitExceededException */
    public function assertCanCreateCategory(Establishment $establishment, int $adding = 1): void
    {
        $plan = $this->planFor($establishment);

        if ($plan->allowsUnlimitedCategories()) {
            return;
        }

        if (($establishment->categories()->count() + $adding) > $plan->max_categories) {
            throw PlanLimitExceededException::categories($plan->max_categories, $plan->name);
        }
    }

    /** @throws PlanLimitExceededException */
    public function assertCanCreateEstablishment(User $user): void
    {
        $plan = $this->defaultPlan();
        $limit = $plan->max_establishments;

        // Considera o maior limite entre os planos das assinaturas ativas do usuario.
        foreach ($user->establishments()->with('subscription.plan')->get() as $establishment) {
            $establishmentPlan = $this->planFor($establishment);

            if ($establishmentPlan->max_establishments === null) {
                return;
            }

            $limit = max((int) $limit, (int) $establishmentPlan->max_establishments);
        }

        if ($limit === null) {
            return;
        }

        if ($user->establishments()->count() >= $limit) {
            throw PlanLimitExceededException::establishments((int) $limit, $plan->name);
        }
    }

    public function hasFeature(Establishment $establishment, PlanFeature $feature): bool
    {
        return $this->planFor($establishment)->hasFeature($feature->value);
    }

    /** @throws PlanLimitExceededException */
    public function assertHasFeature(Establishment $establishment, PlanFeature $feature): void
    {
        $plan = $this->planFor($establishment);

        if (! $plan->hasFeature($feature->value)) {
            throw PlanLimitExceededException::feature($feature->label(), $plan->name);
        }
    }

    /** Resumo usado pelo dashboard e pelas API Resources. */
    public function summary(Establishment $establishment): array
    {
        $plan = $this->planFor($establishment);

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'is_free' => $plan->isFree(),
            ],
            'limits' => [
                'max_products' => $plan->max_products,
                'max_categories' => $plan->max_categories,
                'products_used' => $establishment->products()->count(),
                'categories_used' => $establishment->categories()->count(),
                'products_remaining' => $this->remainingProducts($establishment),
                'categories_remaining' => $this->remainingCategories($establishment),
            ],
            'features' => $plan->features ?? [],
        ];
    }
}

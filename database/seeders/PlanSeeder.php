<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planos comerciais. Idempotente: pode rodar em producao sem duplicar.
 * Novos planos entram apenas adicionando um item neste array.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Tudo que você precisa para colocar seu cardápio no ar.',
                'price_cents' => 0,
                'billing_period' => BillingPeriod::Monthly,
                'trial_days' => 0,
                'max_products' => 10,
                'max_categories' => 5,
                'max_establishments' => 1,
                'features' => [],
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Sem limites, sem a marca da plataforma e com estatísticas.',
                'price_cents' => 3990,
                'billing_period' => BillingPeriod::Monthly,
                'trial_days' => 0,
                'max_products' => null,
                'max_categories' => null,
                'max_establishments' => 3,
                'features' => PlanFeature::values(),
                'is_default' => false,
                'sort_order' => 1,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'currency' => 'BRL', 'is_active' => true],
            );
        }
    }
}

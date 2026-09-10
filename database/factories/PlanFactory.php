<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(1900, 9900),
            'currency' => 'BRL',
            'billing_period' => BillingPeriod::Monthly,
            'trial_days' => 0,
            'max_products' => null,
            'max_categories' => null,
            'max_establishments' => 1,
            'features' => PlanFeature::values(),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
        ];
    }

    /** Plano gratuito padrao: limites rigidos e sem recursos PRO. */
    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Para começar a vender hoje mesmo.',
            'price_cents' => 0,
            'max_products' => 10,
            'max_categories' => 5,
            'max_establishments' => 1,
            'features' => [],
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Sem limites e sem a marca da plataforma.',
            'price_cents' => 3990,
            'max_products' => null,
            'max_categories' => null,
            'max_establishments' => 3,
            'features' => PlanFeature::values(),
            'is_default' => false,
            'sort_order' => 1,
        ]);
    }
}

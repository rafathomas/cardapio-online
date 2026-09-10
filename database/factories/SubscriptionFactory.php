<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Establishment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'gateway' => 'mercadopago',
        ];
    }

    public function onPlan(Plan $plan): static
    {
        return $this->state(fn () => ['plan_id' => $plan->id]);
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function free(Plan $plan): static
    {
        return $this->state(fn () => [
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subDay(),
        ]);
    }
}

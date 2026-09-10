<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Establishment;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'subscription_id' => null,
            'plan_id' => Plan::factory(),
            'gateway' => 'mercadopago',
            'gateway_payment_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'external_reference' => 'sub_'.Str::lower(Str::random(20)),
            'idempotency_key' => (string) Str::uuid(),
            'status' => PaymentStatus::Pending,
            'amount_cents' => 3990,
            'currency' => 'BRL',
            'payment_method' => 'checkout',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Rejected]);
    }
}

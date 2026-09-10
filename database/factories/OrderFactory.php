<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Establishment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(2000, 15000);

        return [
            'establishment_id' => Establishment::factory(),
            'code' => '#'.Str::upper(Str::random(6)),
            'customer_name' => fake()->name(),
            'customer_phone' => '11987654321',
            'delivery_type' => 'pickup',
            'payment_method' => 'Pix',
            'subtotal_cents' => $subtotal,
            'total_cents' => $subtotal,
            'status' => OrderStatus::Received,
            'channel' => 'whatsapp',
        ];
    }
}

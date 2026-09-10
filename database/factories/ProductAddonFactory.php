<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AddonGroup;
use App\Models\ProductAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductAddon> */
class ProductAddonFactory extends Factory
{
    protected $model = ProductAddon::class;

    public function definition(): array
    {
        return [
            'addon_group_id' => AddonGroup::factory(),
            'name' => fake()->randomElement(['Queijo', 'Bacon', 'Ovo', 'Cheddar', 'Catupiry']),
            'price_cents' => fake()->randomElement([200, 300, 500]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

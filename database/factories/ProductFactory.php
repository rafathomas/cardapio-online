<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Establishment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'establishment_id' => Establishment::factory(),
            'category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'price_cents' => fake()->numberBetween(900, 6900),
            'promo_price_cents' => null,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn () => [
            'establishment_id' => $category->establishment_id,
            'category_id' => $category->id,
        ]);
    }

    public function onSale(int $promoCents = 1990): static
    {
        return $this->state(fn (array $attributes) => [
            'price_cents' => max($promoCents + 1000, $attributes['price_cents'] ?? 2990),
            'promo_price_cents' => $promoCents,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}

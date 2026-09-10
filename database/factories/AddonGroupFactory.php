<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AddonGroup;
use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AddonGroup> */
class AddonGroupFactory extends Factory
{
    protected $model = AddonGroup::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'name' => 'Adicionais',
            'is_required' => false,
            'min_options' => 0,
            'max_options' => 5,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function required(int $min = 1, int $max = 1): static
    {
        return $this->state(fn () => [
            'is_required' => true,
            'min_options' => $min,
            'max_options' => $max,
        ]);
    }
}

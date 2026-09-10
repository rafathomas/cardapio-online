<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstablishmentSegment;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Establishment> */
class EstablishmentFactory extends Factory
{
    protected $model = Establishment::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'legal_name' => $name.' LTDA',
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'segment' => fake()->randomElement(EstablishmentSegment::cases()),
            'description' => fake()->sentence(12),
            'phone' => '1133334444',
            'whatsapp' => '11988887777',
            'instagram' => Str::slug($name),
            'address_street' => fake()->streetName(),
            'address_number' => (string) fake()->buildingNumber(),
            'address_district' => 'Centro',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'address_zipcode' => '01001-000',
            'primary_color' => '#E11D48',
            'secondary_color' => '#0F172A',
            'manual_status' => null,
            'timezone' => 'America/Sao_Paulo',
            'is_published' => true,
            'is_indexable' => true,
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => ['manual_status' => Establishment::STATUS_OPEN]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['manual_status' => Establishment::STATUS_CLOSED]);
    }
}

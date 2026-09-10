<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Support\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'price_cents', 'currency', 'billing_period',
    'trial_days', 'max_products', 'max_categories', 'max_establishments',
    'features', 'is_active', 'is_default', 'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'trial_days' => 'integer',
            'max_products' => 'integer',
            'max_categories' => 'integer',
            'max_establishments' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'billing_period' => BillingPeriod::class,
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents, $this->currency);
    }

    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    /** NULL em max_products significa ilimitado. */
    public function allowsUnlimitedProducts(): bool
    {
        return $this->max_products === null;
    }

    public function allowsUnlimitedCategories(): bool
    {
        return $this->max_categories === null;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'establishment_id', 'category_id', 'name', 'slug', 'description',
    'price_cents', 'promo_price_cents', 'image_path',
    'is_active', 'is_featured', 'sort_order',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'promo_price_cents' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<AddonGroup, $this> */
    public function addonGroups(): BelongsToMany
    {
        return $this->belongsToMany(AddonGroup::class)
            ->withPivot('sort_order')
            ->orderBy('addon_group_product.sort_order');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    public function promoPrice(): ?Money
    {
        return $this->promo_price_cents !== null
            ? Money::fromCents($this->promo_price_cents)
            : null;
    }

    /**
     * Preco que o cliente realmente paga. A promocao so vale se for
     * estritamente menor que o preco cheio.
     */
    public function effectivePrice(): Money
    {
        if ($this->promo_price_cents !== null && $this->promo_price_cents < $this->price_cents) {
            return Money::fromCents($this->promo_price_cents);
        }

        return Money::fromCents($this->price_cents);
    }

    public function hasDiscount(): bool
    {
        return $this->promo_price_cents !== null && $this->promo_price_cents < $this->price_cents;
    }

    public function discountPercentage(): int
    {
        if (! $this->hasDiscount() || $this->price_cents === 0) {
            return 0;
        }

        return (int) round((($this->price_cents - $this->promo_price_cents) / $this->price_cents) * 100);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}

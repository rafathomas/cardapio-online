<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price_cents' => $this->price_cents,
            'price_formatted' => $this->price()->format(),
            'promo_price_cents' => $this->promo_price_cents,
            'promo_price_formatted' => $this->promoPrice()?->format(),
            'effective_price_cents' => $this->effectivePrice()->cents,
            'effective_price_formatted' => $this->effectivePrice()->format(),
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->discountPercentage(),
            'image_url' => $this->imageUrl(),
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'addon_groups' => AddonGroupResource::collection($this->whenLoaded('addonGroups')),
        ];
    }
}

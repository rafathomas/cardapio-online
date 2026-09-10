<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'price_formatted' => $this->price()->format(),
            'currency' => $this->currency,
            'billing_period' => $this->billing_period->value,
            'billing_period_label' => $this->billing_period->label(),
            'is_free' => $this->isFree(),
            'max_products' => $this->max_products,
            'max_categories' => $this->max_categories,
            'max_establishments' => $this->max_establishments,
            'features' => $this->features ?? [],
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
        ];
    }
}

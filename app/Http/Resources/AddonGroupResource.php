<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AddonGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AddonGroup */
class AddonGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_required' => $this->is_required,
            'min_options' => $this->min_options,
            'max_options' => $this->max_options,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price_cents' => $addon->price_cents,
                'price_formatted' => $addon->price()->format(),
                'is_active' => $addon->is_active,
            ])->values()),
        ];
    }
}

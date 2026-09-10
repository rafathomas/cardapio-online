<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Establishment */
class EstablishmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'slug' => $this->slug,
            'segment' => $this->segment->value,
            'segment_label' => $this->segment->label(),
            'description' => $this->description,
            'logo_url' => $this->logoUrl(),
            'cover_url' => $this->coverUrl(),
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'instagram' => $this->instagram,
            'address' => [
                'street' => $this->address_street,
                'number' => $this->address_number,
                'complement' => $this->address_complement,
                'district' => $this->address_district,
                'city' => $this->address_city,
                'state' => $this->address_state,
                'zipcode' => $this->address_zipcode,
            ],
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'manual_status' => $this->manual_status,
            'timezone' => $this->timezone,
            'is_open' => $this->isOpen(),
            'is_published' => $this->is_published,
            'is_indexable' => $this->is_indexable,
            'published_at' => $this->published_at?->toIso8601String(),
            'public_url' => $this->publicUrl(),
            'business_hours' => BusinessHourResource::collection($this->whenLoaded('businessHours')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'products_count' => $this->whenCounted('products'),
            'categories_count' => $this->whenCounted('categories'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_type' => $this->delivery_type,
            'address_line' => $this->address_line,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'subtotal_cents' => $this->subtotal_cents,
            'subtotal_formatted' => $this->subtotal()->format(),
            'total_cents' => $this->total_cents,
            'total_formatted' => $this->total()->format(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'channel' => $this->channel,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'addons' => $item->addons ?? [],
                'addons_total_cents' => $item->addons_total_cents,
                'total_cents' => $item->total_cents,
                'total_formatted' => $item->total()->format(),
                'notes' => $item->notes,
            ])->values()),
        ];
    }
}

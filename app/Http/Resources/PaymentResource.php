<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => $this->amount()->format(),
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_type' => $this->payment_type,
            'checkout_url' => $this->checkout_url,
            'qr_code_payload' => $this->qr_code_payload,
            'external_reference' => $this->external_reference,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}

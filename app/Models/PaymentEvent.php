<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'gateway', 'event_key', 'event_type', 'event_action',
    'gateway_resource_id', 'status', 'error_message', 'payload', 'processed_at',
])]
class PaymentEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

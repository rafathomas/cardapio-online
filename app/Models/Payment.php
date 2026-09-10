<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'establishment_id', 'subscription_id', 'plan_id', 'gateway', 'gateway_payment_id',
    'external_reference', 'idempotency_key', 'status', 'status_detail',
    'amount_cents', 'currency', 'payment_method', 'payment_type',
    'checkout_url', 'qr_code_payload', 'approved_at', 'gateway_payload',
])]
#[Hidden(['gateway_payload'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'approved_at' => 'datetime',
            'gateway_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents, $this->currency);
    }

    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::Approved;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'establishment_id', 'plan_id', 'status', 'trial_ends_at',
    'current_period_start', 'current_period_end', 'canceled_at', 'ends_at',
    'gateway', 'gateway_subscription_id',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    #[Scope]
    protected function accessible(Builder $query): void
    {
        $query->whereIn('status', [
            SubscriptionStatus::Trial->value,
            SubscriptionStatus::Active->value,
            SubscriptionStatus::PastDue->value,
        ]);
    }

    public function grantsAccess(): bool
    {
        if (! $this->status->grantsAccess()) {
            return false;
        }

        // Uma assinatura marcada como ativa mas com periodo vencido nao concede acesso.
        if ($this->current_period_end !== null && $this->current_period_end->isPast()) {
            return false;
        }

        if ($this->status === SubscriptionStatus::Trial
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function renewsAt(): ?Carbon
    {
        return $this->current_period_end;
    }
}

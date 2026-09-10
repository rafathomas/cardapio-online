<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionStatusChanged;
use Illuminate\Support\Facades\Log;

/** Observabilidade das transicoes de assinatura. */
class LogSubscriptionChange
{
    public function handle(SubscriptionStatusChanged $event): void
    {
        Log::info('subscription.changed', [
            'subscription_id' => $event->subscription->id,
            'establishment_id' => $event->subscription->establishment_id,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ]);
    }
}

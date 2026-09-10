<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Events\PaymentFailed;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Avisa o dono do estabelecimento sobre o desfecho da cobranca.
 *
 * Os dois metodos sao descobertos automaticamente pelo tipo do parametro,
 * entao nao ha registro manual (que duplicaria as notificacoes).
 * Nunca registra dados do meio de pagamento nos logs.
 */
class NotifyPaymentOutcome implements ShouldQueue
{
    public function handleApproved(PaymentApproved $event): void
    {
        $user = $event->payment->establishment?->user;

        Log::info('payment.approved', [
            'payment_id' => $event->payment->id,
            'establishment_id' => $event->payment->establishment_id,
            'amount_cents' => $event->payment->amount_cents,
        ]);

        $user?->notify(new PaymentApprovedNotification($event->payment));
    }

    public function handleFailed(PaymentFailed $event): void
    {
        $user = $event->payment->establishment?->user;

        Log::warning('payment.failed', [
            'payment_id' => $event->payment->id,
            'establishment_id' => $event->payment->establishment_id,
            'status' => $event->status->value,
        ]);

        $user?->notify(new PaymentFailedNotification($event->payment, $event->status));
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PaymentEvent;
use App\Services\Billing\MercadoPagoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Processa a notificacao fora do ciclo de request.
 *
 * WithoutOverlapping garante que duas entregas do mesmo pagamento nao sejam
 * reconciliadas em paralelo.
 */
class ProcessMercadoPagoWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300];

    public function __construct(public readonly int $paymentEventId) {}

    public function handle(MercadoPagoService $service): void
    {
        $event = PaymentEvent::find($this->paymentEventId);

        if ($event === null) {
            return;
        }

        $service->processEvent($event);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('mp-event-'.$this->paymentEventId))->releaseAfter(30)];
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentEventId;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Billing\MercadoPagoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rede de seguranca para webhooks perdidos.
 *
 * Se uma notificacao nao chegar, este comando reconsulta o gateway e
 * reconcilia os pagamentos que ainda estao pendentes.
 */
class SyncPendingPaymentsCommand extends Command
{
    protected $signature = 'cardapio:sync-payments {--hours=48 : Janela de pagamentos a reconsultar}';

    protected $description = 'Reconsulta no gateway os pagamentos ainda pendentes';

    public function handle(MercadoPagoService $mercadoPago): int
    {
        $since = now()->subHours((int) $this->option('hours'));
        $reconciled = 0;

        Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::InProcess->value])
            ->whereNotNull('gateway_payment_id')
            ->where('created_at', '>=', $since)
            ->chunkById(50, function ($payments) use ($mercadoPago, &$reconciled): void {
                foreach ($payments as $payment) {
                    try {
                        $before = $payment->status;
                        $after = $mercadoPago->syncPayment($payment)->status;

                        if ($before !== $after) {
                            $reconciled++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('payment.sync_failed', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("{$reconciled} pagamento(s) reconciliado(s).");

        return self::SUCCESS;
    }
}

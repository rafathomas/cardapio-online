<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionManager;
use Illuminate\Console\Command;

/**
 * Marca como expiradas as assinaturas cujo periodo pago terminou.
 * Roda pelo scheduler; ver routes/console.php.
 */
class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'cardapio:expire-subscriptions';

    protected $description = 'Expira assinaturas cujo período de vigência terminou';

    public function handle(SubscriptionManager $subscriptions): int
    {
        $expired = $subscriptions->expireOverdue();

        $this->info($expired === 0
            ? 'Nenhuma assinatura para expirar.'
            : "{$expired} assinatura(s) expirada(s).");

        return self::SUCCESS;
    }
}

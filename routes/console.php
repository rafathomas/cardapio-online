<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tarefas agendadas
|--------------------------------------------------------------------------
| Ative com um único cron no servidor:
|   * * * * * cd /caminho && php artisan schedule:run >> /dev/null 2>&1
*/

// Assinaturas vencidas deixam de conceder acesso.
Schedule::command('cardapio:expire-subscriptions')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Rede de segurança caso alguma notificação do gateway se perca.
Schedule::command('cardapio:sync-payments')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Higiene do banco.
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();

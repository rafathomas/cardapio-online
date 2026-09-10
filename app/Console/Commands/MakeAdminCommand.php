<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Promove (ou rebaixa) um usuario a administrador da plataforma.
 * E o caminho seguro para criar o primeiro admin em producao.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'cardapio:make-admin
                            {email : E-mail do usuário}
                            {--revoke : Remove o acesso de administrador}';

    protected $description = 'Concede ou remove acesso de administrador da plataforma';

    public function handle(AuditLogger $audit): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Usuário não encontrado: {$this->argument('email')}");

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');

        $user->forceFill(['is_admin' => ! $revoke])->save();

        $audit->log($revoke ? 'admin.revoked' : 'admin.granted', $user, meta: [
            'target_user_id' => $user->id,
        ]);

        $this->info($revoke
            ? "Acesso de administrador removido de {$user->email}."
            : "{$user->email} agora é administrador da plataforma.");

        return self::SUCCESS;
    }
}

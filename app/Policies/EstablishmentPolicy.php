<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Establishment;
use App\Models\User;

/**
 * Autorizacao base do multi-tenant: um usuario so alcanca os proprios
 * estabelecimentos. Trocar o id na URL nao concede acesso.
 */
class EstablishmentPolicy
{
    /** Administradores da plataforma tem leitura, mas nao escrita silenciosa. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isBlocked()) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return $this->owns($user, $establishment);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $this->owns($user, $establishment);
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $this->owns($user, $establishment);
    }

    public function publish(User $user, Establishment $establishment): bool
    {
        return $this->owns($user, $establishment);
    }

    private function owns(User $user, Establishment $establishment): bool
    {
        return $establishment->user_id === $user->id;
    }
}

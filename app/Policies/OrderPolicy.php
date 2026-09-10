<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/** Acesso derivado da posse do estabelecimento dono do recurso. */
class OrderPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isBlocked() ? false : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): bool
    {
        $establishment = $order->relationLoaded('establishment')
            ? $order->establishment
            : $order->establishment()->first();

        return $establishment !== null && $establishment->user_id === $user->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/** Acesso derivado da posse do estabelecimento dono do recurso. */
class ProductPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isBlocked() ? false : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    private function owns(User $user, Product $product): bool
    {
        $establishment = $product->relationLoaded('establishment')
            ? $product->establishment
            : $product->establishment()->first();

        return $establishment !== null && $establishment->user_id === $user->id;
    }
}

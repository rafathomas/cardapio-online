<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/** Acesso derivado da posse do estabelecimento dono do recurso. */
class CategoryPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isBlocked() ? false : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $this->owns($user, $category);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return $this->owns($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->owns($user, $category);
    }

    private function owns(User $user, Category $category): bool
    {
        $establishment = $category->relationLoaded('establishment')
            ? $category->establishment
            : $category->establishment()->first();

        return $establishment !== null && $establishment->user_id === $user->id;
    }
}

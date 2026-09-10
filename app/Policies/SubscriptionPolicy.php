<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isBlocked() ? false : null;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->owns($user, $subscription);
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $this->owns($user, $subscription);
    }

    private function owns(User $user, Subscription $subscription): bool
    {
        return $subscription->establishment()->first()?->user_id === $user->id;
    }
}

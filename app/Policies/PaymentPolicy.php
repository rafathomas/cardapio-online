<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isBlocked() ? false : null;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->establishment()->first()?->user_id === $user->id;
    }
}

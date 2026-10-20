<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * MDM 03 §15 — Supervisors cannot reopen finalized payroll. Admin only.
 */
class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->isAdmin();
    }

    public function finalize(User $user): bool
    {
        return $user->isAdmin();
    }

    public function reopen(User $user, PayrollPeriod $period): bool
    {
        return $user->isAdmin() && $period->isLocked();
    }

    public function reFinalize(User $user, PayrollPeriod $period): bool
    {
        return $user->isAdmin() && $period->isReopened();
    }
}

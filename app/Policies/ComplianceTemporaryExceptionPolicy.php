<?php

namespace App\Policies;

use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Models\User;

class ComplianceTemporaryExceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, ComplianceTemporaryException $exception): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return $user->can('view', $exception->employee);
        }

        if ($user->isDsp() && $user->employee !== null) {
            return $exception->employee_id === $user->employee->id;
        }

        return false;
    }

    public function create(User $user, ?Employee $employee = null): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $employee === null || $user->can('view', $employee);
    }

    public function revoke(User $user, ComplianceTemporaryException $exception): bool
    {
        return $user->isAdmin();
    }
}

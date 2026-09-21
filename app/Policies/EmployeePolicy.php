<?php

namespace App\Policies;

use App\Enums\JobType;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function viewSupervisorDirectory(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp()) {
            return false;
        }

        if ($user->employee?->is($employee)) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            return $employee->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isAdmin();
    }

    public function overrideWeeklyAvailability(User $user, Employee $employee): bool
    {
        return $user->isAdmin() && $employee->job_type === JobType::Dsp;
    }

    public function viewSensitive(User $user, Employee $employee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            return $employee->supervisor_id === $user->employee->id;
        }

        return false;
    }

    /**
     * Employees are retained for history and must not be hard-deleted.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }
}

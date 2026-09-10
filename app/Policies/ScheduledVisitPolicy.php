<?php

namespace App\Policies;

use App\Models\ScheduledVisit;
use App\Models\User;

class ScheduledVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, ScheduledVisit $scheduledVisit): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            if ($scheduledVisit->supervisor_id === $user->employee->id) {
                return true;
            }

            if ($user->can('view', $scheduledVisit->client)) {
                return true;
            }

            return $user->can('view', $scheduledVisit->employee);
        }

        if ($user->isDsp() && $user->employee) {
            return $scheduledVisit->employee_id === $user->employee->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ScheduledVisit $scheduledVisit): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ScheduledVisit $scheduledVisit): bool
    {
        return false;
    }

    public function restore(User $user, ScheduledVisit $scheduledVisit): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ScheduledVisit $scheduledVisit): bool
    {
        return false;
    }
}

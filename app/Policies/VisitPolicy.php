<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, Visit $visit): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp() && $user->employee) {
            return $visit->employee_id === $user->employee->id;
        }

        $visit->loadMissing('scheduledVisit.client', 'scheduledVisit.employee');

        return $user->can('view', $visit->scheduledVisit);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Visit $visit): bool
    {
        return false;
    }

    public function delete(User $user, Visit $visit): bool
    {
        return false;
    }

    public function restore(User $user, Visit $visit): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Visit $visit): bool
    {
        return false;
    }
}

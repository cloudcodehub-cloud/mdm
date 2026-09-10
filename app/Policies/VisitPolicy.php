<?php

namespace App\Policies;

use App\Enums\VisitStatus;
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

    public function recordTask(User $user, Visit $visit): bool
    {
        return $this->operate($user, $visit);
    }

    public function updateNotes(User $user, Visit $visit): bool
    {
        return $this->operate($user, $visit);
    }

    public function clockOut(User $user, Visit $visit): bool
    {
        return $this->operate($user, $visit);
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

    private function operate(User $user, Visit $visit): bool
    {
        if (! $user->isDsp() || $user->employee === null || ! $user->employee->isActiveDsp()) {
            return false;
        }

        return $visit->employee_id === $user->employee->id
            && $visit->status === VisitStatus::InProgress;
    }
}

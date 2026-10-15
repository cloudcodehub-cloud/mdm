<?php

namespace App\Policies;

use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;

class AttendanceCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, AttendanceCorrection $correction): bool
    {
        $correction->loadMissing('scheduledVisit');

        return $user->can('view', $correction->scheduledVisit);
    }

    public function create(User $user, ScheduledVisit $scheduledVisit): bool
    {
        if (! $user->can('view', $scheduledVisit)) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! $user->isDsp() || $user->employee === null || ! $user->employee->isActiveDsp()) {
            return false;
        }

        if ($scheduledVisit->employee_id !== $user->employee->id) {
            return false;
        }

        $visit = $scheduledVisit->relationLoaded('visit')
            ? $scheduledVisit->visit
            : $scheduledVisit->visit()->first();

        return $visit instanceof Visit && $visit->status === VisitStatus::Completed;
    }

    public function approve(User $user, AttendanceCorrection $correction): bool
    {
        return $user->isAdmin() && $correction->isPending();
    }

    public function reject(User $user, AttendanceCorrection $correction): bool
    {
        return $this->approve($user, $correction);
    }

    public function update(User $user, AttendanceCorrection $correction): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceCorrection $correction): bool
    {
        return false;
    }
}

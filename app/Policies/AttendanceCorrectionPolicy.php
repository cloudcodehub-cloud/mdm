<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\ScheduledVisit;
use App\Models\User;

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
        if ($user->isDsp()) {
            return false;
        }

        if ($user->isAdmin()) {
            return $user->can('view', $scheduledVisit);
        }

        return $user->isSupervisor() && $user->can('view', $scheduledVisit);
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

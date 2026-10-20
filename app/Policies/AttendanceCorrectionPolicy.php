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

        $scheduledVisit->loadMissing('visits');

        // DSP may request correction for their own completed Visit segment(s).
        $ownCompleted = $scheduledVisit->visits->first(
            fn (Visit $visit): bool => $visit->employee_id === $user->employee->id
                && $visit->status === VisitStatus::Completed,
        );

        if ($ownCompleted !== null) {
            return true;
        }

        // Legacy single-Visit path when visits relation is empty but latest visit matches.
        if ($scheduledVisit->employee_id !== $user->employee->id) {
            return false;
        }

        $visit = $scheduledVisit->relationLoaded('visit')
            ? $scheduledVisit->visit
            : $scheduledVisit->visit()->first();

        return $visit instanceof Visit && $visit->status === VisitStatus::Completed;
    }

    /**
     * MDM 01 §2.44 / MDM 05 §L / MASTER §4.8 — Supervisors review DSP-originated
     * corrections in caseload. No self-approval. Supervisor-initiated requests need Admin.
     * Admin retains agency-wide review authority.
     */
    public function approve(User $user, AttendanceCorrection $correction): bool
    {
        if (! $correction->isPending()) {
            return false;
        }

        $correction->loadMissing('scheduledVisit');

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isSupervisor() || $user->employee === null) {
            return false;
        }

        if (! $user->can('view', $correction->scheduledVisit)) {
            return false;
        }

        // Independent approval: initiator cannot also approve.
        if ($correction->requested_by_user_id === $user->id) {
            return false;
        }

        $requester = $correction->requestedBy;

        // Supervisor may review DSP-originated requests only (MDM 05 §L).
        return $requester !== null && $requester->isDsp();
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

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

    public function recordSafety(User $user, Visit $visit): bool
    {
        return $this->operate($user, $visit);
    }

    public function issueSafetyInstruction(User $user, Visit $visit): bool
    {
        if ($visit->status !== VisitStatus::InProgress) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            $visit->loadMissing('client');

            return $visit->client->supervisor_id === $user->employee->id;
        }

        return false;
    }

    /**
     * MDM 05-03 / MASTER §10.5 — caseload Supervisor (or Admin) may administratively
     * close an Active Visit. DSP EVV must never be impersonated.
     */
    public function administrativelyClose(User $user, Visit $visit): bool
    {
        if ($visit->status !== VisitStatus::InProgress || $visit->clocked_out_at !== null) {
            return false;
        }

        if ($visit->isAdministrativelyClosed()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            $visit->loadMissing('client');

            return $visit->client->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function acknowledgeCriticalAlert(User $user, Visit $visit): bool
    {
        return $this->operate($user, $visit);
    }

    public function acknowledgeSafetyInstruction(User $user, Visit $visit): bool
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
        // Already-live visit work may be finished while Active or Archive Pending (MDM 03).
        if (! $user->isDsp() || $user->employee === null || ! $user->employee->canFinishActiveVisit()) {
            return false;
        }

        return $visit->employee_id === $user->employee->id
            && $visit->status === VisitStatus::InProgress;
    }
}

<?php

namespace App\Policies;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Models\Visit;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($incident->status === IncidentStatus::Draft) {
            return $this->ownsDraft($user, $incident);
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp() && $user->employee) {
            return $incident->reporter_employee_id === $user->employee->id;
        }

        if ($user->isSupervisor() && $user->employee) {
            $incident->loadMissing('client');

            return $incident->responsible_supervisor_id === $user->employee->id
                || $incident->client?->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function create(User $user, ?Visit $visit = null): bool
    {
        if ($visit === null) {
            return $user->isDsp() && $user->employee?->isActiveDsp() === true;
        }

        return $user->can('recordTask', $visit);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $incident->isDraft() && $this->ownsDraft($user, $incident);
    }

    public function submit(User $user, Incident $incident): bool
    {
        return $this->update($user, $incident);
    }

    public function discard(User $user, Incident $incident): bool
    {
        return $this->update($user, $incident);
    }

    public function acknowledge(User $user, Incident $incident): bool
    {
        if ($incident->status !== IncidentStatus::Submitted) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            $incident->loadMissing('client');

            return $incident->responsible_supervisor_id === $user->employee->id
                || $incident->client?->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function manage(User $user, Incident $incident): bool
    {
        if ($incident->status === IncidentStatus::Draft) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor() && $user->employee) {
            $incident->loadMissing('client');

            return $incident->responsible_supervisor_id === $user->employee->id
                || $incident->client?->supervisor_id === $user->employee->id;
        }

        return false;
    }

    public function amend(User $user, Incident $incident): bool
    {
        if (! $incident->status->isFormal() || $incident->status === IncidentStatus::Closed) {
            return false;
        }

        $isReporter = $user->isDsp()
            && $user->employee !== null
            && $incident->reporter_employee_id === $user->employee->id;

        return $isReporter || $this->manage($user, $incident);
    }

    public function close(User $user, Incident $incident): bool
    {
        return $this->manage($user, $incident)
            && $incident->status !== IncidentStatus::Closed
            && $incident->status !== IncidentStatus::Draft;
    }

    public function reopen(User $user, Incident $incident): bool
    {
        return $user->isAdmin() && $incident->status === IncidentStatus::Closed;
    }

    private function ownsDraft(User $user, Incident $incident): bool
    {
        return $user->isDsp()
            && $user->employee !== null
            && $incident->reporter_employee_id === $user->employee->id
            && $incident->isDraft();
    }
}

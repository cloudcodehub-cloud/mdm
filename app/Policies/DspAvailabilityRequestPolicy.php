<?php

namespace App\Policies;

use App\Enums\JobType;
use App\Models\DspAvailabilityRequest;
use App\Models\Employee;
use App\Models\User;

class DspAvailabilityRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isSupervisor() && $user->employee !== null);
    }

    public function view(User $user, DspAvailabilityRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp() && $user->employee?->id === $request->employee_id) {
            return true;
        }

        return $user->isSupervisor()
            && $user->employee !== null
            && $request->employee->supervisor_id === $user->employee->id;
    }

    public function create(User $user): bool
    {
        return $user->isDsp() && $user->employee !== null;
    }

    public function review(User $user, DspAvailabilityRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupervisor()
            && $user->employee !== null
            && $request->employee->supervisor_id === $user->employee->id;
    }

    public function override(User $user, Employee $employee): bool
    {
        return $user->isAdmin() && $employee->job_type === JobType::Dsp;
    }
}

<?php

namespace App\Policies;

use App\Enums\JobType;
use App\Models\EmployeeProfileChangeRequest;
use App\Models\User;

class EmployeeProfileChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, EmployeeProfileChangeRequest $request): bool
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

    public function review(User $user, EmployeeProfileChangeRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupervisor()
            && $user->employee !== null
            && $request->employee->job_type === JobType::Dsp
            && $request->employee->supervisor_id === $user->employee->id;
    }
}

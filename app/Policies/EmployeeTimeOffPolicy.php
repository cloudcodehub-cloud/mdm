<?php

namespace App\Policies;

use App\Enums\JobType;
use App\Models\EmployeeTimeOff;
use App\Models\User;

class EmployeeTimeOffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, EmployeeTimeOff $timeOff): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDsp() && $user->employee?->id === $timeOff->employee_id) {
            return true;
        }

        return $user->isSupervisor()
            && $user->employee !== null
            && $timeOff->employee->supervisor_id === $user->employee->id;
    }

    public function review(User $user, EmployeeTimeOff $timeOff): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupervisor()
            && $user->employee !== null
            && $timeOff->employee->job_type === JobType::Dsp
            && $timeOff->employee->supervisor_id === $user->employee->id;
    }
}

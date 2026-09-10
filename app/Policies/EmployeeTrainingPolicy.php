<?php

namespace App\Policies;

use App\Models\EmployeeTraining;
use App\Models\User;

class EmployeeTrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, EmployeeTraining $employeeTraining): bool
    {
        return $user->can('view', $employeeTraining->employee);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EmployeeTraining $employeeTraining): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EmployeeTraining $employeeTraining): bool
    {
        return false;
    }

    public function restore(User $user, EmployeeTraining $employeeTraining): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, EmployeeTraining $employeeTraining): bool
    {
        return false;
    }
}

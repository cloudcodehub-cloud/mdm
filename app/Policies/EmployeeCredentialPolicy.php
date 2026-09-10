<?php

namespace App\Policies;

use App\Models\EmployeeCredential;
use App\Models\User;

class EmployeeCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function view(User $user, EmployeeCredential $employeeCredential): bool
    {
        return $user->can('view', $employeeCredential->employee);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EmployeeCredential $employeeCredential): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EmployeeCredential $employeeCredential): bool
    {
        return false;
    }

    public function restore(User $user, EmployeeCredential $employeeCredential): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, EmployeeCredential $employeeCredential): bool
    {
        return false;
    }
}

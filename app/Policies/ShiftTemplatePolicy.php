<?php

namespace App\Policies;

use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return false;
    }

    public function restore(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ShiftTemplate $shiftTemplate): bool
    {
        return false;
    }
}

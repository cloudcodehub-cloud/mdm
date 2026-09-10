<?php

namespace App\Policies;

use App\Models\SkipReason;
use App\Models\User;

class SkipReasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, SkipReason $skipReason): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SkipReason $skipReason): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SkipReason $skipReason): bool
    {
        return false;
    }

    public function restore(User $user, SkipReason $skipReason): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, SkipReason $skipReason): bool
    {
        return false;
    }
}

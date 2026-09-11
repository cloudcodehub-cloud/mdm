<?php

namespace App\Policies;

use App\Models\CareService;
use App\Models\User;

class CareServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CareService $careService): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CareService $careService): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CareService $careService): bool
    {
        return false;
    }
}

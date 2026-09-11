<?php

namespace App\Policies;

use App\Models\CarePlan;
use App\Models\User;

class CarePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, CarePlan $carePlan): bool
    {
        return $user->can('view', $carePlan->client);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function update(User $user, CarePlan $carePlan): bool
    {
        return $user->can('manageCarePlan', $carePlan->client);
    }

    public function delete(User $user, CarePlan $carePlan): bool
    {
        return false;
    }

    public function restore(User $user, CarePlan $carePlan): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CarePlan $carePlan): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\CarePlanTaskTemplate;
use App\Models\User;

class CarePlanTaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, CarePlanTaskTemplate $carePlanTaskTemplate): bool
    {
        return $user->can('view', $carePlanTaskTemplate->carePlan);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CarePlanTaskTemplate $carePlanTaskTemplate): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CarePlanTaskTemplate $carePlanTaskTemplate): bool
    {
        return false;
    }

    public function restore(User $user, CarePlanTaskTemplate $carePlanTaskTemplate): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CarePlanTaskTemplate $carePlanTaskTemplate): bool
    {
        return false;
    }
}

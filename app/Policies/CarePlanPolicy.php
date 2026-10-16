<?php

namespace App\Policies;

use App\Enums\DspClientAccessLevel;
use App\Models\CarePlan;
use App\Models\User;
use App\Services\DspClientAccessService;

class CarePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, CarePlan $carePlan): bool
    {
        if (! $user->can('view', $carePlan->client)) {
            return false;
        }

        if (! $user->isDsp()) {
            return true;
        }

        return app(DspClientAccessService::class)->levelFor($user, $carePlan->client)
            === DspClientAccessLevel::Full;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function update(User $user, CarePlan $carePlan): bool
    {
        return $user->can('manageCarePlan', $carePlan->client);
    }

    /**
     * Decision 06-20 — Admin-only controlled effective-date correction.
     */
    public function correctEffectiveDate(User $user, CarePlan $carePlan): bool
    {
        return $user->isAdmin() && $user->can('view', $carePlan->client);
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

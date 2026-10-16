<?php

namespace App\Policies;

use App\Models\ComplianceRequirement;
use App\Models\User;

class ComplianceRequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, ComplianceRequirement $requirement): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}

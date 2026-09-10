<?php

namespace App\Policies;

use App\Models\OrganizationSetting;
use App\Models\User;

class OrganizationSettingPolicy
{
    public function view(User $user, OrganizationSetting $organizationSetting): bool
    {
        return true;
    }

    public function update(User $user, OrganizationSetting $organizationSetting): bool
    {
        return $user->isAdmin();
    }
}

<?php

namespace App\Policies;

use App\Models\TaskCatalogItem;
use App\Models\User;

class TaskCatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor() || $user->isDsp();
    }

    public function view(User $user, TaskCatalogItem $taskCatalogItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TaskCatalogItem $taskCatalogItem): bool
    {
        return $user->isAdmin();
    }
}

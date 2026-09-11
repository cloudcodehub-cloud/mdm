<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementService;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canMessage();
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return app(AnnouncementService::class)->isVisibleTo($user, $announcement);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupervisor() && $announcement->author_id === $user->id;
    }
}

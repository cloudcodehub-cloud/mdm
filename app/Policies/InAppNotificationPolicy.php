<?php

namespace App\Policies;

use App\Models\InAppNotification;
use App\Models\User;

class InAppNotificationPolicy
{
    public function view(User $user, InAppNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }

    public function update(User $user, InAppNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}

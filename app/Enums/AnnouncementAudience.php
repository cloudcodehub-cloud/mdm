<?php

namespace App\Enums;

use App\Models\User;

enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case Admins = 'admins';
    case Supervisors = 'supervisors';
    case Dsps = 'dsps';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Admins => 'Admins',
            self::Supervisors => 'Supervisors',
            self::Dsps => 'DSPs',
        };
    }

    public function matches(User $user): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Admins => $user->isAdmin(),
            self::Supervisors => $user->isSupervisor(),
            self::Dsps => $user->isDsp(),
        };
    }
}

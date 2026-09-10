<?php

namespace App\Enums;

enum JobType: string
{
    case Supervisor = 'supervisor';
    case Dsp = 'dsp';
    case Other = 'other';

    public function requiresLogin(): bool
    {
        return $this === self::Supervisor || $this === self::Dsp;
    }

    public function toRole(): Role
    {
        return match ($this) {
            self::Supervisor => Role::Supervisor,
            self::Dsp => Role::Dsp,
            self::Other => throw new \LogicException('Other job types do not map to a login role.'),
        };
    }
}

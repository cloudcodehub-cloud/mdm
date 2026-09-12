<?php

namespace App\Enums;

enum JobType: string
{
    case Supervisor = 'supervisor';
    case Dsp = 'dsp';
    case Admin = 'admin';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dsp => 'DSP',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
            self::Other => 'Other',
        };
    }

    public function requiresLogin(): bool
    {
        return $this === self::Supervisor || $this === self::Dsp || $this === self::Admin;
    }

    public function toRole(): Role
    {
        return match ($this) {
            self::Supervisor => Role::Supervisor,
            self::Dsp => Role::Dsp,
            self::Admin => Role::Admin,
            self::Other => throw new \LogicException('Other job types do not map to a login role.'),
        };
    }
}

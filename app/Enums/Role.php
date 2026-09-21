<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Supervisor = 'SUPERVISOR';
    case Dsp = 'DSP';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Supervisor => 'Supervisor',
            self::Dsp => 'DSP',
        };
    }
}

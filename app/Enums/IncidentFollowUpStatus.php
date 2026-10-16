<?php

namespace App\Enums;

enum IncidentFollowUpStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Completed => 'Completed',
        };
    }
}

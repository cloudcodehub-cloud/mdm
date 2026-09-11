<?php

namespace App\Enums;

enum TaskPreferredTiming: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case DuringVisit = 'during_visit';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Evening => 'Evening',
            self::DuringVisit => 'During Visit',
        };
    }
}

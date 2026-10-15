<?php

namespace App\Enums;

enum VisitSafetyCheckStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Late = 'late';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Late => 'Late',
            self::Missed => 'Missed',
        };
    }

    public function isDocumented(): bool
    {
        return match ($this) {
            self::Completed => true,
            self::Late, self::Missed => true,
            self::Pending => false,
        };
    }

    public function requiresReason(): bool
    {
        return match ($this) {
            self::Late, self::Missed => true,
            self::Pending, self::Completed => false,
        };
    }
}

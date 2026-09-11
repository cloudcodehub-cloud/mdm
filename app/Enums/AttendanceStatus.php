<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AttendanceStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Late = 'late';
    case Missed = 'missed';
    case Exception = 'exception';
    case ManuallyAdjusted = 'manually_adjusted';

    public function label(): string
    {
        return match ($this) {
            self::ManuallyAdjusted => 'Manually Adjusted',
            default => Str::headline($this->value),
        };
    }
}

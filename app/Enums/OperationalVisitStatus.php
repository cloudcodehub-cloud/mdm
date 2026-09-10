<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum OperationalVisitStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Late = 'late';
    case Attention = 'attention';
    case Exception = 'exception';

    public function label(): string
    {
        return match ($this) {
            self::Late => 'Late',
            self::Attention => 'Attention needed',
            self::Exception => 'Exception',
            default => Str::headline($this->value),
        };
    }
}

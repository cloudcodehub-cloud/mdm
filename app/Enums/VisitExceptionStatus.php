<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum VisitExceptionStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return Str::headline($this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }
}

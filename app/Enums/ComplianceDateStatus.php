<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceDateStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Missing = 'missing';
    case Pending = 'pending';
    case Revoked = 'revoked';
    case InProgress = 'in_progress';

    public function label(): string
    {
        return match ($this) {
            self::ExpiringSoon => 'Expiring Soon',
            self::InProgress => 'In Progress',
            default => Str::headline($this->value),
        };
    }

    public function needsAttention(): bool
    {
        return $this !== self::Valid;
    }
}

<?php

namespace App\Enums;

enum PayrollPeriodAuditAction: string
{
    case Finalized = 'finalized';
    case Reopened = 'reopened';
    case ReFinalized = 're_finalized';

    public function label(): string
    {
        return match ($this) {
            self::Finalized => 'Finalized',
            self::Reopened => 'Reopened',
            self::ReFinalized => 'Re-finalized',
        };
    }
}

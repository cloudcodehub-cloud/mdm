<?php

namespace App\Enums;

enum TemporarySupervisorCoverageAuditAction: string
{
    case Granted = 'granted';
    case Modified = 'modified';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Granted',
            self::Modified => 'Modified',
            self::Revoked => 'Revoked',
        };
    }
}

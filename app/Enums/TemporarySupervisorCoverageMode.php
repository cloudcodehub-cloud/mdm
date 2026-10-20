<?php

namespace App\Enums;

enum TemporarySupervisorCoverageMode: string
{
    case FullCaseload = 'full_caseload';
    case Selective = 'selective';

    public function label(): string
    {
        return match ($this) {
            self::FullCaseload => 'Full caseload coverage',
            self::Selective => 'Selective coverage',
        };
    }
}

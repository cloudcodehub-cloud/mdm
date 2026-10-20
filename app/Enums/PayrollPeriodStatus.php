<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Finalized = 'finalized';
    case ReopenedForCorrection = 'reopened_for_correction';

    public function label(): string
    {
        return match ($this) {
            self::Finalized => 'Finalized / Payroll Export Ready',
            self::ReopenedForCorrection => 'Reopened for Correction',
        };
    }

    public function isExportReady(): bool
    {
        return $this === self::Finalized;
    }

    public function isLocked(): bool
    {
        return $this === self::Finalized;
    }
}

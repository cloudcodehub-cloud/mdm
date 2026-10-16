<?php

namespace App\Enums;

enum ComplianceStaffingStatus: string
{
    case None = 'none';
    case Grace = 'grace';
    case AtRisk = 'at_risk';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Grace => 'Grace Visit',
            self::AtRisk => 'At Risk / Action Required',
        };
    }

    public function isGrace(): bool
    {
        return $this === self::Grace;
    }

    public function isAtRisk(): bool
    {
        return $this === self::AtRisk;
    }
}

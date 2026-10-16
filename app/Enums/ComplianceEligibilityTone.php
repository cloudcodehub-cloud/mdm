<?php

namespace App\Enums;

enum ComplianceEligibilityTone: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Eligible',
            self::Yellow => 'Warning',
            self::Red => 'Hard Stop',
        };
    }
}

<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceEnforcementLevel: string
{
    case Informational = 'informational';
    case Warning = 'warning';
    case Required = 'required';
    case HardStop = 'hard_stop';

    public function label(): string
    {
        return match ($this) {
            self::HardStop => 'Hard Stop / Mandatory for Service',
            default => Str::headline($this->value),
        };
    }

    public function blocksService(): bool
    {
        return $this === self::HardStop;
    }
}

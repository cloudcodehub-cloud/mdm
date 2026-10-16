<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceOverridePolicy: string
{
    case NonOverrideable = 'non_overrideable';
    case TemporaryException = 'temporary_exception';

    public function label(): string
    {
        return match ($this) {
            self::NonOverrideable => 'Non-Overrideable',
            self::TemporaryException => 'Temporary Exception Eligible',
            default => Str::headline($this->value),
        };
    }

    public function allowsTemporaryException(): bool
    {
        return $this === self::TemporaryException;
    }
}

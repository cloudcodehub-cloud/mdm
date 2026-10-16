<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceApplicability: string
{
    case All = 'all';
    case Dsp = 'dsp';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All applicable employees',
            self::Dsp => 'DSPs',
            default => Str::headline($this->value),
        };
    }
}

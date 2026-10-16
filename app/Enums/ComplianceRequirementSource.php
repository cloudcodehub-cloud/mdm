<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceRequirementSource: string
{
    case Credential = 'credential';
    case Training = 'training';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}

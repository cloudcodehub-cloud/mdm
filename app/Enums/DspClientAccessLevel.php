<?php

namespace App\Enums;

enum DspClientAccessLevel: string
{
    case None = 'none';
    case Preparation = 'preparation';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No access',
            self::Preparation => 'Preparation access',
            self::Full => 'Full operational access',
        };
    }

    public function allowsHistory(): bool
    {
        return $this === self::Full;
    }

    public function allowsFullProfile(): bool
    {
        return $this === self::Full;
    }
}

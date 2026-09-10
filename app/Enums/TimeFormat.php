<?php

namespace App\Enums;

enum TimeFormat: string
{
    case TwelveHour = '12';
    case TwentyFourHour = '24';

    public function label(): string
    {
        return match ($this) {
            self::TwelveHour => '12-hour',
            self::TwentyFourHour => '24-hour',
        };
    }
}

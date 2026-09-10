<?php

namespace App\Enums;

enum DateFormat: string
{
    case MonthDayYear = 'm/d/Y';
    case DayMonthYear = 'd/m/Y';
    case Iso = 'Y-m-d';

    public function label(): string
    {
        return match ($this) {
            self::MonthDayYear => 'MM/DD/YYYY',
            self::DayMonthYear => 'DD/MM/YYYY',
            self::Iso => 'YYYY-MM-DD',
        };
    }
}

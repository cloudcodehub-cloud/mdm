<?php

namespace App\Enums;

enum TaskRecurrence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Annual => 'Annual',
            self::Custom => 'Custom',
        };
    }

    public function defaultIntervalWeeks(): ?int
    {
        return match ($this) {
            self::Weekly => 1,
            self::Biweekly => 2,
            default => null,
        };
    }
}

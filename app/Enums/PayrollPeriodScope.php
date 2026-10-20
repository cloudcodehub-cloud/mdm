<?php

namespace App\Enums;

enum PayrollPeriodScope: string
{
    case Period = 'period';
    case Employee = 'employee';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::Period => 'Full pay period',
            self::Employee => 'One employee',
            self::Visit => 'One timesheet / visit record',
        };
    }
}

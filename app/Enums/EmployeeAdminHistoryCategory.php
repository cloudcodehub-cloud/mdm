<?php

namespace App\Enums;

enum EmployeeAdminHistoryCategory: string
{
    case Lifecycle = 'lifecycle';
    case SchedulingImpact = 'scheduling_impact';
    case Login = 'login';

    public function label(): string
    {
        return match ($this) {
            self::Lifecycle => 'Lifecycle',
            self::SchedulingImpact => 'Scheduling impact',
            self::Login => 'Login',
        };
    }
}

<?php

namespace App\Enums;

enum EmployeeAdminHistoryCategory: string
{
    case Lifecycle = 'lifecycle';
    case SchedulingImpact = 'scheduling_impact';
    case Login = 'login';
    case ProfileChange = 'profile_change';
    case Leave = 'leave';
    case ComplianceStaffing = 'compliance_staffing';

    public function label(): string
    {
        return match ($this) {
            self::Lifecycle => 'Lifecycle',
            self::SchedulingImpact => 'Scheduling impact',
            self::Login => 'Login',
            self::ProfileChange => 'Personal information',
            self::Leave => 'Leave',
            self::ComplianceStaffing => 'Compliance staffing',
        };
    }
}

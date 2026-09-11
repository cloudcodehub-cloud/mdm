<?php

namespace App\Enums;

enum ReportType: string
{
    case EmployeeAttendance = 'employee-attendance';
    case ClientVisits = 'client-visits';
    case LateMissedVisits = 'late-missed-visits';
    case TaskCompletion = 'task-completion';
    case CredentialTrainingExpiration = 'credential-training-expiration';
    case Compliance = 'compliance';
    case Exceptions = 'exceptions';
    case PayrollHours = 'payroll-hours';

    public function title(): string
    {
        return match ($this) {
            self::EmployeeAttendance => 'Employee Attendance',
            self::ClientVisits => 'Client Visits',
            self::LateMissedVisits => 'Late / Missed Visits',
            self::TaskCompletion => 'Task Completion',
            self::CredentialTrainingExpiration => 'Credential / Training Expiration',
            self::Compliance => 'Compliance',
            self::Exceptions => 'Exceptions',
            self::PayrollHours => 'Payroll Hours',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EmployeeAttendance => 'Scheduled visits with effective attendance in the operational timezone.',
            self::ClientVisits => 'Visit execution records for clients in the selected period.',
            self::LateMissedVisits => 'Visits that are late or missed using the same attendance rules as Attendance.',
            self::TaskCompletion => 'Care-plan task outcomes captured on visits.',
            self::CredentialTrainingExpiration => 'Credentials and training that are expired or expiring soon.',
            self::Compliance => 'Workforce credential and training compliance status.',
            self::Exceptions => 'Visit exceptions on the permitted caseload.',
            self::PayrollHours => 'Hours-only payroll totals from completed visits and effective attendance.',
        };
    }

    public function usesDateRange(): bool
    {
        return $this !== self::Compliance;
    }

    public function usesClientFilter(): bool
    {
        return match ($this) {
            self::CredentialTrainingExpiration, self::Compliance, self::PayrollHours => false,
            default => true,
        };
    }

    public function usesStatusFilter(): bool
    {
        return $this !== self::PayrollHours;
    }

    public function defaultDateRange(): bool
    {
        return match ($this) {
            self::CredentialTrainingExpiration, self::Compliance => false,
            default => true,
        };
    }
}

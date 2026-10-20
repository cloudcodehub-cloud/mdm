<?php

namespace App\Enums;

enum OrganizationSettingChangeKey: string
{
    case OfficialAgencyName = 'official_agency_name';
    case AgencyOperationalTimezone = 'agency_operational_timezone';
    case ScheduleTimezoneMigration = 'schedule_timezone_migration';
    case IncidentAcknowledgmentMinutes = 'incident_acknowledgment_minutes';
    case StaffingNeededUrgencyHours = 'staffing_needed_urgency_hours';
    case CredentialExpiringSoonDays = 'credential_expiring_soon_days';

    public function label(): string
    {
        return match ($this) {
            self::OfficialAgencyName => 'Official Agency Name',
            self::AgencyOperationalTimezone => 'Agency Operational Timezone',
            self::ScheduleTimezoneMigration => 'Schedule timezone migration',
            self::IncidentAcknowledgmentMinutes => 'Incident acknowledgment threshold',
            self::StaffingNeededUrgencyHours => 'Staffing Needed urgency threshold',
            self::CredentialExpiringSoonDays => 'Credential expiring-soon threshold',
        };
    }

    public function isHighRisk(): bool
    {
        return match ($this) {
            self::OfficialAgencyName,
            self::AgencyOperationalTimezone,
            self::ScheduleTimezoneMigration => true,
            default => false,
        };
    }
}

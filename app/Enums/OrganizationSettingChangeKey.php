<?php

namespace App\Enums;

enum OrganizationSettingChangeKey: string
{
    case OfficialAgencyName = 'official_agency_name';
    case AgencyOperationalTimezone = 'agency_operational_timezone';
    case IncidentAcknowledgmentMinutes = 'incident_acknowledgment_minutes';
    case StaffingNeededUrgencyHours = 'staffing_needed_urgency_hours';
    case CredentialExpiringSoonDays = 'credential_expiring_soon_days';

    public function label(): string
    {
        return match ($this) {
            self::OfficialAgencyName => 'Official Agency Name',
            self::AgencyOperationalTimezone => 'Agency Operational Timezone',
            self::IncidentAcknowledgmentMinutes => 'Incident acknowledgment threshold',
            self::StaffingNeededUrgencyHours => 'Staffing Needed urgency threshold',
            self::CredentialExpiringSoonDays => 'Credential expiring-soon threshold',
        };
    }

    public function isHighRisk(): bool
    {
        return match ($this) {
            self::OfficialAgencyName, self::AgencyOperationalTimezone => true,
            default => false,
        };
    }
}

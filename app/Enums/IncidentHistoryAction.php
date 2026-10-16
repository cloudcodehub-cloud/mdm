<?php

namespace App\Enums;

enum IncidentHistoryAction: string
{
    case DraftCreated = 'draft_created';
    case DraftUpdated = 'draft_updated';
    case DraftDiscarded = 'draft_discarded';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case AcknowledgmentOverdue = 'acknowledgment_overdue';
    case StatusChanged = 'status_changed';
    case ThresholdConfigured = 'threshold_configured';

    public function label(): string
    {
        return match ($this) {
            self::DraftCreated => 'Draft created',
            self::DraftUpdated => 'Draft updated',
            self::DraftDiscarded => 'Draft discarded',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::AcknowledgmentOverdue => 'Overdue for acknowledgment',
            self::StatusChanged => 'Lifecycle changed',
            self::ThresholdConfigured => 'Acknowledgment threshold configured',
        };
    }
}

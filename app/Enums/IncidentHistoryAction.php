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
    case OfficialClassificationUpdated = 'official_classification_updated';
    case ReviewAdded = 'review_added';
    case InvestigationUpdated = 'investigation_updated';
    case FollowUpCreated = 'follow_up_created';
    case FollowUpCompleted = 'follow_up_completed';
    case FollowUpReassigned = 'follow_up_reassigned';
    case ReportingRecordAdded = 'reporting_record_added';
    case AmendmentAdded = 'amendment_added';
    case Closed = 'closed';
    case Reopened = 'reopened';

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
            self::OfficialClassificationUpdated => 'Official classification updated',
            self::ReviewAdded => 'Management review added',
            self::InvestigationUpdated => 'Investigation fields updated',
            self::FollowUpCreated => 'Follow-up created',
            self::FollowUpCompleted => 'Follow-up completed',
            self::FollowUpReassigned => 'Follow-up reassigned',
            self::ReportingRecordAdded => 'Formal reporting record added',
            self::AmendmentAdded => 'Amendment added',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
        };
    }
}

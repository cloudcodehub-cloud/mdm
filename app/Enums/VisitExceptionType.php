<?php

namespace App\Enums;

enum VisitExceptionType: string
{
    case GpsUnavailable = 'gps_unavailable';
    case LateStart = 'late_start';
    case ClientRefusal = 'client_refusal';
    case CriticalTaskSkipped = 'critical_task_skipped';
    case OtherVisitException = 'other_visit_exception';
    case MissedSafetyCheck = 'missed_safety_check';
    case ExtendedVisit = 'extended_visit';
    case OpenVisitEscalation = 'open_visit_escalation';
    case HandoffCoverageGap = 'handoff_coverage_gap';

    public function label(): string
    {
        return match ($this) {
            self::GpsUnavailable => 'GPS / location',
            self::LateStart => 'Late start',
            self::ClientRefusal => 'Client refusal',
            self::CriticalTaskSkipped => 'Critical task skipped',
            self::OtherVisitException => 'Unfinished-task exception',
            self::MissedSafetyCheck => 'Missed safety check',
            self::ExtendedVisit => 'Extended visit',
            self::OpenVisitEscalation => 'Open visit escalation',
            self::HandoffCoverageGap => 'Handoff coverage gap',
        };
    }

    /**
     * Presentation priority derived from existing exception types.
     * Critical skips, client refusals, unfinished required work, missed safety,
     * and open-visit escalation are high.
     */
    public function isHighPriority(): bool
    {
        return match ($this) {
            self::CriticalTaskSkipped,
            self::ClientRefusal,
            self::OtherVisitException,
            self::MissedSafetyCheck,
            self::OpenVisitEscalation => true,
            self::GpsUnavailable, self::LateStart, self::ExtendedVisit, self::HandoffCoverageGap => false,
        };
    }

    public function priority(): string
    {
        return $this->isHighPriority() ? 'high' : 'standard';
    }
}

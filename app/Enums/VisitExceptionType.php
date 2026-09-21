<?php

namespace App\Enums;

enum VisitExceptionType: string
{
    case GpsUnavailable = 'gps_unavailable';
    case ClientRefusal = 'client_refusal';
    case CriticalTaskSkipped = 'critical_task_skipped';
    case OtherVisitException = 'other_visit_exception';

    public function label(): string
    {
        return match ($this) {
            self::GpsUnavailable => 'GPS / location',
            self::ClientRefusal => 'Client refusal',
            self::CriticalTaskSkipped => 'Critical task skipped',
            self::OtherVisitException => 'Unfinished-task exception',
        };
    }

    /**
     * Presentation priority derived from existing exception types.
     * Critical skips, client refusals, and unfinished required work are high.
     */
    public function isHighPriority(): bool
    {
        return match ($this) {
            self::CriticalTaskSkipped, self::ClientRefusal, self::OtherVisitException => true,
            self::GpsUnavailable => false,
        };
    }

    public function priority(): string
    {
        return $this->isHighPriority() ? 'high' : 'standard';
    }
}

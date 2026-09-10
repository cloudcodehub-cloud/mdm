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
}

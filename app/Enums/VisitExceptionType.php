<?php

namespace App\Enums;

enum VisitExceptionType: string
{
    case GpsUnavailable = 'gps_unavailable';
    case ClientRefusal = 'client_refusal';
    case CriticalTaskSkipped = 'critical_task_skipped';
    case OtherVisitException = 'other_visit_exception';
}

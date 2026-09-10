<?php

namespace App\Enums;

enum ScheduledVisitStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}

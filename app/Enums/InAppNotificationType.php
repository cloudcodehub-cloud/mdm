<?php

namespace App\Enums;

enum InAppNotificationType: string
{
    case Message = 'message';
    case Announcement = 'announcement';
    case Schedule = 'schedule';
    case Availability = 'availability';
    case AvailabilityOverride = 'availability_override';
    case Profile = 'profile';
    case Compliance = 'compliance';
    case Incident = 'incident';
}

<?php

namespace App\Enums;

enum InAppNotificationType: string
{
    case Message = 'message';
    case Announcement = 'announcement';
    case Schedule = 'schedule';
    case Availability = 'availability';
    case Profile = 'profile';
}

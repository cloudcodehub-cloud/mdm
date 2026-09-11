<?php

namespace App\Enums;

enum InAppNotificationType: string
{
    case Message = 'message';
    case Announcement = 'announcement';
}

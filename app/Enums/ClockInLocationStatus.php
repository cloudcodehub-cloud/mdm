<?php

namespace App\Enums;

enum ClockInLocationStatus: string
{
    case Captured = 'captured';
    case Denied = 'denied';
    case Unavailable = 'unavailable';
    case Unsupported = 'unsupported';
}

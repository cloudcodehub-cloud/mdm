<?php

namespace App\Enums;

enum ClockInLocationMethod: string
{
    case BrowserGps = 'browser_gps';
    case GpsUnavailable = 'gps_unavailable';
}

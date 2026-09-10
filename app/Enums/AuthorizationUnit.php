<?php

namespace App\Enums;

enum AuthorizationUnit: string
{
    case Hour = 'hour';
    case Visit = 'visit';
    case Day = 'day';
}

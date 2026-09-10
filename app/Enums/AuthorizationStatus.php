<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Cancelled = 'cancelled';
}

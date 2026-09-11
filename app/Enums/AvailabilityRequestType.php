<?php

namespace App\Enums;

enum AvailabilityRequestType: string
{
    case Weekly = 'weekly';
    case Exception = 'exception';
}

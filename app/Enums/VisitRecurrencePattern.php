<?php

namespace App\Enums;

enum VisitRecurrencePattern: string
{
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Custom = 'custom';
}

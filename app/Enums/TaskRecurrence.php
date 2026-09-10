<?php

namespace App\Enums;

enum TaskRecurrence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Annual = 'annual';
    case Custom = 'custom';
}

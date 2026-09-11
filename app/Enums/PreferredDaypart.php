<?php

namespace App\Enums;

enum PreferredDaypart: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Overnight = 'overnight';
}

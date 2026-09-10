<?php

namespace App\Enums;

enum VisitTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}

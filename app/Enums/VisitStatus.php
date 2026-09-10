<?php

namespace App\Enums;

enum VisitStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}

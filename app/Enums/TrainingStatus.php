<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
}

<?php

namespace App\Enums;

enum JobType: string
{
    case Supervisor = 'supervisor';
    case Dsp = 'dsp';
    case Other = 'other';
}

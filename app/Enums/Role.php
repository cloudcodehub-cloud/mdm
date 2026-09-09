<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Supervisor = 'SUPERVISOR';
    case Dsp = 'DSP';
}

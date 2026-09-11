<?php

namespace App\Enums;

enum SeriesEditScope: string
{
    case This = 'this';
    case Future = 'future';
    case Series = 'series';
}

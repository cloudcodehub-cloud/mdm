<?php

namespace App\Enums;

enum EducationLevel: string
{
    case HighSchool = 'high_school';
    case College = 'college';

    public function label(): string
    {
        return match ($this) {
            self::HighSchool => 'High School',
            self::College => 'College',
        };
    }
}

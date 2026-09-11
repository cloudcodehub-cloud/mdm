<?php

namespace App\Enums;

enum TaskCatalogCategory: string
{
    case PersonalCare = 'personal_care';
    case Mobility = 'mobility';
    case Meals = 'meals';
    case Medication = 'medication';
    case Household = 'household';
    case Community = 'community';
    case HealthSafety = 'health_safety';
    case Behavioral = 'behavioral';
    case Documentation = 'documentation';

    public function label(): string
    {
        return match ($this) {
            self::PersonalCare => 'Personal Care / ADLs',
            self::Mobility => 'Mobility & Positioning',
            self::Meals => 'Meals & Nutrition',
            self::Medication => 'Medication Support',
            self::Household => 'Household / IADLs',
            self::Community => 'Community & Transportation',
            self::HealthSafety => 'Health & Safety',
            self::Behavioral => 'Behavioral / Skill Development',
            self::Documentation => 'Documentation / Visit Closeout',
        };
    }
}

<?php

namespace App\Enums;

enum ProfileCompletenessStatus: string
{
    case Critical = 'critical';
    case Attention = 'attention';
    case Healthy = 'healthy';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::Attention => 'Needs attention',
            self::Healthy => 'Healthy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Attention => 'warning',
            self::Healthy => 'success',
        };
    }

    public static function fromPercent(int $percent): self
    {
        if ($percent <= 39) {
            return self::Critical;
        }

        if ($percent <= 69) {
            return self::Attention;
        }

        return self::Healthy;
    }
}

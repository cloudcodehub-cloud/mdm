<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Discharged = 'discharged';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Discharged => 'Discharged',
            self::Archived => 'Archived',
        };
    }

    public function allowsFutureScheduling(): bool
    {
        return $this === self::Active;
    }

    public function isServiceEligible(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}

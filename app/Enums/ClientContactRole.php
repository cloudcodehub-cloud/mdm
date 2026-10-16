<?php

namespace App\Enums;

enum ClientContactRole: string
{
    case Emergency = 'emergency';
    case Guardian = 'guardian';
    case FamilyOther = 'family_other';

    public function label(): string
    {
        return match ($this) {
            self::Emergency => 'Emergency Contact',
            self::Guardian => 'Guardian / Authorized Representative',
            self::FamilyOther => 'Family / Other Contact',
        };
    }

    /**
     * DSP may see emergency contact details for assigned service context.
     */
    public function visibleToDsp(): bool
    {
        return $this === self::Emergency;
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

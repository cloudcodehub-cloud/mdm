<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case EmploymentEnded = 'employment_ended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::EmploymentEnded => 'Employment Ended',
            self::Archived => 'Archived',
        };
    }

    /**
     * Login/account access is separate from service eligibility.
     * Suspended employees keep login; Employment Ended / Archived do not.
     */
    public function allowsLogin(): bool
    {
        return $this === self::Active || $this === self::Suspended;
    }

    /**
     * Prospective service / scheduling eligibility for this employment state.
     */
    public function allowsService(): bool
    {
        return $this === self::Active;
    }

    public function releasesFutureAssignments(): bool
    {
        return in_array($this, [self::Suspended, self::EmploymentEnded, self::Archived], true);
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

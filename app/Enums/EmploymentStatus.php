<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case EmploymentEnded = 'employment_ended';
    /** MDM 03: Admin archive accepted, finalization deferred until blocking live work ends. */
    case ArchivePending = 'archive_pending';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::EmploymentEnded => 'Employment Ended',
            self::ArchivePending => 'Archive Pending',
            self::Archived => 'Archived',
        };
    }

    /**
     * Login/account access is separate from service eligibility.
     * Suspended employees keep login; Employment Ended / Archived do not.
     * Archive Pending keeps login so live work can finish (see Employee::allowsLogin()).
     */
    public function allowsLogin(): bool
    {
        return $this === self::Active
            || $this === self::Suspended
            || $this === self::ArchivePending;
    }

    /**
     * Prospective service / scheduling eligibility for this employment state.
     * Archive Pending never receives NEW assignments; it may only finish already-live work.
     */
    public function allowsService(): bool
    {
        return $this === self::Active;
    }

    /**
     * Already-started visit work (tasks, notes, clock-out) may be completed.
     */
    public function allowsFinishingActiveVisit(): bool
    {
        return $this === self::Active || $this === self::ArchivePending;
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

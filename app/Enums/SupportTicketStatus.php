<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Unassigned = 'unassigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Unassigned => 'Unassigned',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
            self::Duplicate => 'Duplicate',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Unassigned, self::InProgress => true,
            default => false,
        };
    }

    public function isReopenable(): bool
    {
        return $this === self::Resolved;
    }
}

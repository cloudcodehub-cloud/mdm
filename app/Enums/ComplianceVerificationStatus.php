<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ComplianceVerificationStatus: string
{
    case Verified = 'verified';
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending Review',
            default => Str::headline($this->value),
        };
    }

    public function isAuthoritative(): bool
    {
        return $this === self::Verified;
    }
}

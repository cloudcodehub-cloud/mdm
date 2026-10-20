<?php

namespace App\Enums;

enum AdminMessageReviewPurpose: string
{
    case AgencyAdministration = 'agency_administration';
    case Investigation = 'investigation';
    case Safety = 'safety';
    case Compliance = 'compliance';
    case AuthorizedManagement = 'authorized_management';

    public function label(): string
    {
        return match ($this) {
            self::AgencyAdministration => 'Agency administration',
            self::Investigation => 'Investigation',
            self::Safety => 'Safety',
            self::Compliance => 'Compliance',
            self::AuthorizedManagement => 'Authorized management purpose',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

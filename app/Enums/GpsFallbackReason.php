<?php

namespace App\Enums;

enum GpsFallbackReason: string
{
    case PermissionDenied = 'permission_denied';
    case GpsUnavailable = 'gps_unavailable';
    case DeviceIssue = 'device_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PermissionDenied => 'Location permission denied',
            self::GpsUnavailable => 'GPS unavailable / weak signal',
            self::DeviceIssue => 'Device issue',
            self::Other => 'Other',
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return list<array{value: string, label: string, requires_note: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $reason): array => [
                'value' => $reason->value,
                'label' => $reason->label(),
                'requires_note' => $reason->requiresNote(),
            ],
            self::cases(),
        );
    }
}

<?php

namespace App\Enums;

enum GpsFallbackReason: string
{
    case PermissionDenied = 'permission_denied';
    case GpsUnavailable = 'gps_unavailable';
    case PoorSignal = 'poor_signal';
    case DeviceIssue = 'device_issue';
    case IndoorAccuracy = 'indoor_accuracy';
    case AppTechnicalIssue = 'app_technical_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PermissionDenied => 'Location permission unavailable',
            self::GpsUnavailable => 'GPS/location service unavailable',
            self::PoorSignal => 'Poor/no signal',
            self::DeviceIssue => 'Device issue',
            self::IndoorAccuracy => 'Indoor/location accuracy issue',
            self::AppTechnicalIssue => 'MDM/app technical issue',
            self::Other => 'Other',
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function storedReasonFrom(array $payload): ?string
    {
        $reasonCode = isset($payload['gps_fallback_reason'])
            ? self::tryFrom((string) $payload['gps_fallback_reason'])
            : null;

        if ($reasonCode === null) {
            return isset($payload['unavailable_reason'])
                ? (is_string($payload['unavailable_reason']) ? trim($payload['unavailable_reason']) : null)
                : null;
        }

        if ($reasonCode->requiresNote()) {
            $note = $payload['gps_fallback_note'] ?? $payload['unavailable_reason'] ?? null;

            return is_string($note) && trim($note) !== '' ? trim($note) : null;
        }

        return $reasonCode->label();
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

<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case HardwareDevice = 'hardware_device';
    case NetworkInternet = 'network_internet';
    case EmailPasswordAccess = 'email_password_access';
    case SoftwareInstallation = 'software_installation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HardwareDevice => 'Hardware / Device',
            self::NetworkInternet => 'Network / Internet',
            self::EmailPasswordAccess => 'Email / Password / Access',
            self::SoftwareInstallation => 'Software / Installation',
            self::Other => 'Other',
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

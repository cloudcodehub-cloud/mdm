<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use InvalidArgumentException;

/**
 * Phone shell navigation (MDM 13 §18.6): Home + 3 shortcuts + More.
 * Shortcuts are preferences only — authorization remains server-side on each route.
 */
final class MobileNavigation
{
    public const HOME_KEY = 'dashboard';

    public const SHORTCUT_COUNT = 3;

    /**
     * Stable destination keys aligned with the shared role navigation catalog.
     *
     * @var array<string, array{title: string, route: string, roles: list<string>}>
     */
    private const DESTINATIONS = [
        'dashboard' => [
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
        'employees' => [
            'title' => 'Employees',
            'route' => 'employees.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'clients' => [
            'title' => 'Clients',
            'route' => 'clients.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'scheduled-visits' => [
            'title' => 'Scheduled Visits',
            'route' => 'scheduled-visits.index',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
        'my-availability' => [
            'title' => 'My Availability',
            'route' => 'my-availability.index',
            'roles' => ['DSP'],
        ],
        'my-compliance' => [
            'title' => 'My Compliance',
            'route' => 'my-compliance.index',
            'roles' => ['DSP'],
        ],
        'availability-requests' => [
            'title' => 'Availability Requests',
            'route' => 'availability-requests.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'operations' => [
            'title' => 'Operations',
            'route' => 'operations.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'incidents' => [
            'title' => 'Incidents',
            'route' => 'incidents.index',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
        'supervisors' => [
            'title' => 'Supervisors',
            'route' => 'supervisors.index',
            'roles' => ['ADMIN'],
        ],
        'attendance' => [
            'title' => 'Attendance',
            'route' => 'attendance.index',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
        'compliance' => [
            'title' => 'Compliance',
            'route' => 'compliance.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'reports' => [
            'title' => 'Reports',
            'route' => 'reports.index',
            'roles' => ['ADMIN', 'SUPERVISOR'],
        ],
        'documents' => [
            'title' => 'Documents',
            'route' => 'admin.documents.index',
            'roles' => ['ADMIN'],
        ],
        'messages' => [
            'title' => 'Messages',
            'route' => 'messages.index',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
        'support-tickets' => [
            'title' => 'Support Tickets',
            'route' => 'support-tickets.index',
            'roles' => ['ADMIN', 'SUPERVISOR', 'DSP'],
        ],
    ];

    /**
     * Frozen role defaults (MDM 13):
     * DSP — Visits, Schedule, Messages
     * Supervisor — Schedule, Incidents, Messages
     * Admin — Operations, Incidents, Messages
     *
     * @return list<string>
     */
    public static function defaultsFor(Role $role): array
    {
        return match ($role) {
            Role::Dsp => ['scheduled-visits', 'my-availability', 'messages'],
            Role::Supervisor => ['scheduled-visits', 'incidents', 'messages'],
            Role::Admin => ['operations', 'incidents', 'messages'],
        };
    }

    /**
     * @return list<string>
     */
    public static function authorizedKeysFor(Role $role): array
    {
        $roleValue = $role->value;

        return array_values(array_filter(
            array_keys(self::DESTINATIONS),
            static fn (string $key): bool => in_array($roleValue, self::DESTINATIONS[$key]['roles'], true),
        ));
    }

    /**
     * Shortcut-selectable destinations exclude the fixed Home slot.
     *
     * @return list<string>
     */
    public static function shortcutKeysFor(Role $role): array
    {
        return array_values(array_filter(
            self::authorizedKeysFor($role),
            static fn (string $key): bool => $key !== self::HOME_KEY,
        ));
    }

    public static function isAuthorized(Role $role, string $key): bool
    {
        $destination = self::DESTINATIONS[$key] ?? null;

        return $destination !== null
            && in_array($role->value, $destination['roles'], true);
    }

    /**
     * @param  list<mixed>|null  $stored
     * @return list<string>
     */
    public static function resolveShortcutKeys(Role $role, ?array $stored): array
    {
        $defaults = self::defaultsFor($role);
        $allowed = self::shortcutKeysFor($role);

        if ($stored === null || count($stored) !== self::SHORTCUT_COUNT) {
            return $defaults;
        }

        $resolved = [];

        foreach ($stored as $index => $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true) || in_array($key, $resolved, true)) {
                $resolved[] = self::fallbackKey($defaults, $allowed, $resolved, $index);

                continue;
            }

            $resolved[] = $key;
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function sanitizeShortcutKeys(Role $role, array $keys): array
    {
        if (count($keys) !== self::SHORTCUT_COUNT) {
            throw new InvalidArgumentException('Mobile navigation requires exactly three shortcuts.');
        }

        $allowed = self::shortcutKeysFor($role);
        $sanitized = [];

        foreach ($keys as $key) {
            if (! in_array($key, $allowed, true) || in_array($key, $sanitized, true)) {
                throw new InvalidArgumentException('Mobile navigation shortcuts must be unique authorized destinations.');
            }

            $sanitized[] = $key;
        }

        return $sanitized;
    }

    /**
     * @return array{
     *     home: array{key: string, title: string, href: string},
     *     shortcuts: list<array{key: string, title: string, href: string}>,
     *     more: list<array{key: string, title: string, href: string}>,
     *     available: list<array{key: string, title: string, href: string}>,
     *     defaults: list<string>
     * }
     */
    public static function sharedFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $role = $user->role;
        $shortcutKeys = self::resolveShortcutKeys($role, $user->mobile_nav_shortcuts);
        $home = self::destinationPayload(self::HOME_KEY);
        $more = [];

        foreach (self::authorizedKeysFor($role) as $key) {
            $more[] = self::destinationPayload($key);
        }

        $available = [];

        foreach (self::shortcutKeysFor($role) as $key) {
            $available[] = self::destinationPayload($key);
        }

        return [
            'home' => $home,
            'shortcuts' => array_map(
                static fn (string $key): array => self::destinationPayload($key),
                $shortcutKeys,
            ),
            'more' => $more,
            'available' => $available,
            'defaults' => self::defaultsFor($role),
        ];
    }

    /**
     * @return array{key: string, title: string, href: string}
     */
    private static function destinationPayload(string $key): array
    {
        $destination = self::DESTINATIONS[$key]
            ?? throw new InvalidArgumentException("Unknown mobile navigation destination [{$key}].");

        return [
            'key' => $key,
            'title' => $destination['title'],
            'href' => route($destination['route']),
        ];
    }

    /**
     * @param  list<string>  $defaults
     * @param  list<string>  $allowed
     * @param  list<string>  $already
     */
    private static function fallbackKey(array $defaults, array $allowed, array $already, int $index): string
    {
        $candidate = $defaults[$index] ?? null;

        if (is_string($candidate) && in_array($candidate, $allowed, true) && ! in_array($candidate, $already, true)) {
            return $candidate;
        }

        foreach ($allowed as $key) {
            if (! in_array($key, $already, true)) {
                return $key;
            }
        }

        return $defaults[0] ?? $allowed[0] ?? self::HOME_KEY;
    }
}

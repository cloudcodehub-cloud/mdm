<?php

namespace App\Services;

use App\Enums\Appearance;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\OrganizationSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    private ?OrganizationSetting $resolved = null;

    public function current(): OrganizationSetting
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $this->resolved = OrganizationSetting::query()->first();

        if ($this->resolved === null) {
            $this->resolved = OrganizationSetting::query()->create([
                'organization_name' => config('app.name', 'MDM - Magic Data Management'),
                'timezone' => 'America/New_York',
                'date_format' => DateFormat::MonthDayYear,
                'time_format' => TimeFormat::TwelveHour,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
            ]);
        }

        return $this->resolved;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOrganization(array $data): OrganizationSetting
    {
        if (isset($data['timezone'])) {
            $this->assertValidTimezone((string) $data['timezone']);
        }

        $settings = $this->current();
        $settings->fill($data);
        $settings->save();
        $this->resolved = $settings->fresh() ?? $settings;

        return $this->resolved;
    }

    public function timezone(): string
    {
        $timezone = $this->current()->timezone;

        if (! $this->isValidTimezone($timezone)) {
            return 'UTC';
        }

        return $timezone;
    }

    public function now(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->toImmutable();
    }

    /**
     * Calendar date in the organization operational timezone.
     */
    public function today(?CarbonInterface $now = null): string
    {
        return $this->localNow($now)->toDateString();
    }

    public function todayStart(?CarbonInterface $now = null): CarbonInterface
    {
        return $this->localNow($now)->startOfDay();
    }

    public function localNow(?CarbonInterface $now = null): CarbonInterface
    {
        return $this->toLocal($this->now($now));
    }

    public function toLocal(CarbonInterface $instant): CarbonInterface
    {
        return $instant->toImmutable()->timezone($this->timezone());
    }

    /**
     * Interpret a service-date wall-clock time in the operational timezone.
     * Historical UTC timestamps are never rewritten.
     */
    public function at(string $date, string $time): CarbonInterface
    {
        return Carbon::parse($date.' '.$this->normalizedTime($time), $this->timezone())->toImmutable();
    }

    public function credentialExpiringSoonDays(): int
    {
        return $this->current()->credential_expiring_soon_days;
    }

    public function formatDate(CarbonInterface $value): string
    {
        $local = $value->toImmutable()->timezone($this->timezone());

        return $local->format($this->current()->date_format->value);
    }

    public function formatTime(CarbonInterface $value): string
    {
        $local = $this->toLocal($value);

        return $this->current()->time_format === TimeFormat::TwelveHour
            ? $local->format('g:i A')
            : $local->format('H:i');
    }

    public function updateAppearance(User $user, Appearance $appearance): User
    {
        $user->appearance = $appearance;
        $user->save();

        return $user;
    }

    public function appearanceFrom(?User $user, ?string $cookie): Appearance
    {
        if ($user?->appearance instanceof Appearance) {
            return $user->appearance;
        }

        return Appearance::tryFrom((string) $cookie) ?? Appearance::System;
    }

    public function timezoneLabel(string $timezone): string
    {
        foreach ($this->commonTimezones() as $option) {
            if ($option['value'] === $timezone) {
                return $option['label'];
            }
        }

        return $this->friendlyTimezoneLabel($timezone);
    }

    /**
     * @return array<string, mixed>
     */
    public function shared(): array
    {
        $settings = $this->current();

        return [
            'organization_name' => $settings->organization_name,
            'timezone' => $this->timezone(),
            'timezone_label' => $this->timezoneLabel($this->timezone()),
            'date_format' => $settings->date_format->value,
            'time_format' => $settings->time_format->value,
            'first_day_of_week' => $settings->first_day_of_week,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function dateFormatOptions(): array
    {
        return array_map(
            fn (DateFormat $format): array => [
                'value' => $format->value,
                'label' => $format->label(),
            ],
            DateFormat::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function timeFormatOptions(): array
    {
        return array_map(
            fn (TimeFormat $format): array => [
                'value' => $format->value,
                'label' => $format->label(),
            ],
            TimeFormat::cases(),
        );
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function firstDayOfWeekOptions(): array
    {
        return [
            ['value' => 0, 'label' => 'Sunday'],
            ['value' => 1, 'label' => 'Monday'],
            ['value' => 2, 'label' => 'Tuesday'],
            ['value' => 3, 'label' => 'Wednesday'],
            ['value' => 4, 'label' => 'Thursday'],
            ['value' => 5, 'label' => 'Friday'],
            ['value' => 6, 'label' => 'Saturday'],
        ];
    }

    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    public function timezoneOptions(): array
    {
        $common = $this->commonTimezones();
        $commonIds = array_column($common, 'value');
        $options = $common;

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            if (in_array($identifier, $commonIds, true)) {
                continue;
            }

            $options[] = [
                'value' => $identifier,
                'label' => $this->friendlyTimezoneLabel($identifier),
                'group' => 'All timezones',
            ];
        }

        return $options;
    }

    public function assertValidTimezone(string $timezone): void
    {
        if (! $this->isValidTimezone($timezone)) {
            throw ValidationException::withMessages([
                'timezone' => 'Select a valid IANA timezone.',
            ]);
        }
    }

    public function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    private function commonTimezones(): array
    {
        return [
            ['value' => 'America/New_York', 'label' => 'Eastern Time (EST/EDT)', 'group' => 'United States'],
            ['value' => 'America/Chicago', 'label' => 'Central Time (CST/CDT)', 'group' => 'United States'],
            ['value' => 'America/Denver', 'label' => 'Mountain Time (MST/MDT)', 'group' => 'United States'],
            ['value' => 'America/Los_Angeles', 'label' => 'Pacific Time (PST/PDT)', 'group' => 'United States'],
        ];
    }

    private function friendlyTimezoneLabel(string $timezone): string
    {
        $parts = explode('/', str_replace('_', ' ', $timezone));
        $city = $parts[array_key_last($parts)];
        $region = $parts[0];

        try {
            $now = $this->now()->timezone($timezone);
            $abbreviation = $now->format('T');
        } catch (\Exception) {
            return $timezone;
        }

        if ($region === $city) {
            return sprintf('%s (%s)', $timezone, $abbreviation);
        }

        return sprintf('%s (%s)', $city, $abbreviation);
    }

    private function normalizedTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return substr($time, 0, 8);
    }
}

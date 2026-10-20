<?php

namespace App\Services;

use App\Enums\Appearance;
use App\Enums\DateFormat;
use App\Enums\OrganizationSettingChangeKey;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\OrganizationSetting;
use App\Models\OrganizationSettingChangeEvent;
use App\Models\ScheduledVisit;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
                'country' => 'United States',
                'timezone' => 'America/New_York',
                'date_format' => DateFormat::MonthDayYear,
                'time_format' => TimeFormat::TwelveHour,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => (int) config('mdm.incident.acknowledgment_minutes_default', 60),
                'staffing_needed_urgency_hours' => (int) config('mdm.staffing.urgency_hours_default', 24),
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
        $officialNameReason = isset($data['official_name_change_reason'])
            ? (string) $data['official_name_change_reason']
            : null;
        $officialNameConfirmed = (bool) ($data['confirm_official_name_change'] ?? false);
        unset(
            $data['logo'],
            $data['remove_logo'],
            $data['official_name_change_reason'],
            $data['confirm_official_name_change'],
            $data['timezone_change_reason'],
            $data['confirm_timezone_change'],
            $data['timezone_effective_at'],
            $data['cancel_pending_timezone'],
        );

        $previousOfficialName = (string) $settings->organization_name;
        $previousAckMinutes = (int) $settings->incident_acknowledgment_minutes;
        $previousStaffingHours = (int) ($settings->staffing_needed_urgency_hours ?? 0);
        $previousCredentialDays = (int) $settings->credential_expiring_soon_days;

        $settings->fill($data);
        $settings->save();
        $this->resolved = $settings->fresh() ?? $settings;

        $actor = auth()->user();
        if ($actor instanceof User) {
            if (array_key_exists('organization_name', $data)
                && trim((string) $data['organization_name']) !== trim($previousOfficialName)) {
                $this->recordSettingChange(
                    OrganizationSettingChangeKey::OfficialAgencyName,
                    $previousOfficialName,
                    (string) $this->resolved->organization_name,
                    $actor,
                    $officialNameReason,
                    null,
                    ['confirmed' => $officialNameConfirmed],
                );
            }

            if (array_key_exists('incident_acknowledgment_minutes', $data)) {
                app(IncidentService::class)->recordThresholdChange(
                    $actor,
                    $previousAckMinutes,
                    (int) $this->resolved->incident_acknowledgment_minutes,
                );
                $this->recordSettingChange(
                    OrganizationSettingChangeKey::IncidentAcknowledgmentMinutes,
                    (string) $previousAckMinutes,
                    (string) $this->resolved->incident_acknowledgment_minutes,
                    $actor,
                    null,
                    null,
                    ['applies_prospectively' => true],
                );
            }

            if (array_key_exists('staffing_needed_urgency_hours', $data)
                && (int) $data['staffing_needed_urgency_hours'] !== $previousStaffingHours) {
                $this->recordSettingChange(
                    OrganizationSettingChangeKey::StaffingNeededUrgencyHours,
                    (string) $previousStaffingHours,
                    (string) $this->resolved->staffing_needed_urgency_hours,
                    $actor,
                );
            }

            if (array_key_exists('credential_expiring_soon_days', $data)
                && (int) $data['credential_expiring_soon_days'] !== $previousCredentialDays) {
                $this->recordSettingChange(
                    OrganizationSettingChangeKey::CredentialExpiringSoonDays,
                    (string) $previousCredentialDays,
                    (string) $this->resolved->credential_expiring_soon_days,
                    $actor,
                );
            }
        }

        return $this->resolved;
    }

    /**
     * MDM 11-02 / 11-10 — schedule a future-effective Agency Operational Timezone change.
     * Does not rewrite historical timestamps or silently reschedule existing visits.
     *
     * @param  array<string, mixed>  $impactPreview
     */
    public function scheduleTimezoneChange(
        User $actor,
        string $newTimezone,
        CarbonInterface $effectiveAt,
        string $reason,
        bool $confirmed,
        array $impactPreview = [],
    ): OrganizationSetting {
        $this->assertValidTimezone($newTimezone);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_timezone_change' => 'Confirm the Agency Operational Timezone change before saving.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'timezone_change_reason' => 'A Reason for Change is required for Agency Operational Timezone changes.',
            ]);
        }

        $settings = $this->current();
        $previous = $settings->timezone;

        if ($newTimezone === $previous
            && $settings->pending_timezone === null) {
            throw ValidationException::withMessages([
                'timezone' => 'Select a different Agency Operational Timezone.',
            ]);
        }

        $effectiveAt = $effectiveAt->toImmutable();
        $now = $this->now();

        if ($effectiveAt->lte($now)) {
            $settings->timezone = $newTimezone;
            $settings->pending_timezone = null;
            $settings->pending_timezone_effective_at = null;
            $settings->pending_timezone_reason = null;
            $settings->pending_timezone_scheduled_by = null;
            $settings->pending_timezone_scheduled_at = null;
            $settings->save();
            $this->resolved = $settings->fresh() ?? $settings;

            $this->recordSettingChange(
                OrganizationSettingChangeKey::AgencyOperationalTimezone,
                $previous,
                $newTimezone,
                $actor,
                $reason,
                $now,
                [
                    'confirmed' => true,
                    'applied_immediately' => true,
                    'historical_timestamps_preserved' => true,
                    'future_schedules_retain_stamped_timezone' => true,
                    'impact_preview' => $impactPreview,
                ],
            );

            return $this->resolved;
        }

        $settings->pending_timezone = $newTimezone;
        $settings->pending_timezone_effective_at = $effectiveAt->utc();
        $settings->pending_timezone_reason = $reason;
        $settings->pending_timezone_scheduled_by = $actor->id;
        $settings->pending_timezone_scheduled_at = $now->utc();
        $settings->save();
        $this->resolved = $settings->fresh() ?? $settings;

        $this->recordSettingChange(
            OrganizationSettingChangeKey::AgencyOperationalTimezone,
            $previous,
            $newTimezone,
            $actor,
            $reason,
            $effectiveAt->utc(),
            [
                'confirmed' => true,
                'status' => 'scheduled',
                'historical_timestamps_preserved' => true,
                'future_schedules_retain_stamped_timezone' => true,
                'impact_preview' => $impactPreview !== [] ? $impactPreview : $this->timezoneChangeImpactPreview($newTimezone),
            ],
        );

        return $this->resolved;
    }

    public function cancelPendingTimezoneChange(User $actor, string $reason = 'Cancelled pending timezone change.'): OrganizationSetting
    {
        $settings = $this->current();

        if ($settings->pending_timezone === null) {
            return $settings;
        }

        $previousPending = (string) $settings->pending_timezone;
        $previousEffective = $settings->pending_timezone_effective_at;

        $settings->pending_timezone = null;
        $settings->pending_timezone_effective_at = null;
        $settings->pending_timezone_reason = null;
        $settings->pending_timezone_scheduled_by = null;
        $settings->pending_timezone_scheduled_at = null;
        $settings->save();
        $this->resolved = $settings->fresh() ?? $settings;

        $this->recordSettingChange(
            OrganizationSettingChangeKey::AgencyOperationalTimezone,
            $previousPending,
            $settings->timezone,
            $actor,
            $reason,
            $previousEffective,
            ['status' => 'cancelled', 'confirmed' => true],
        );

        return $this->resolved;
    }

    /**
     * Apply a due pending timezone change. Existing schedule_timezone stamps are never rewritten.
     */
    public function applyDuePendingTimezone(?CarbonInterface $now = null): OrganizationSetting
    {
        $settings = $this->current();
        $now = $now ?? $this->now();

        if ($settings->pending_timezone === null || $settings->pending_timezone_effective_at === null) {
            return $settings;
        }

        if ($settings->pending_timezone_effective_at->gt($now)) {
            return $settings;
        }

        $previous = $settings->timezone;
        $newTimezone = (string) $settings->pending_timezone;
        $reason = (string) ($settings->pending_timezone_reason ?? 'Pending timezone change became effective.');
        $effectiveAt = $settings->pending_timezone_effective_at;
        $actorId = $settings->pending_timezone_scheduled_by;

        $this->assertValidTimezone($newTimezone);

        $settings->timezone = $newTimezone;
        $settings->pending_timezone = null;
        $settings->pending_timezone_effective_at = null;
        $settings->pending_timezone_reason = null;
        $settings->pending_timezone_scheduled_by = null;
        $settings->pending_timezone_scheduled_at = null;
        $settings->save();
        $this->resolved = $settings->fresh() ?? $settings;

        $actor = $actorId !== null ? User::query()->find($actorId) : null;

        OrganizationSettingChangeEvent::query()->create([
            'setting_key' => OrganizationSettingChangeKey::AgencyOperationalTimezone,
            'summary' => 'Scheduled Agency Operational Timezone change applied.',
            'previous_value' => $previous,
            'new_value' => $newTimezone,
            'reason' => $reason,
            'effective_at' => $effectiveAt,
            'actor_user_id' => $actor?->id,
            'meta' => [
                'status' => 'applied',
                'historical_timestamps_preserved' => true,
                'future_schedules_retain_stamped_timezone' => true,
            ],
        ]);

        return $this->resolved;
    }

    /**
     * @return array{
     *     current_timezone: string,
     *     proposed_timezone: string,
     *     current_today: string,
     *     proposed_today: string,
     *     future_scheduled_visits: int,
     *     preserves_schedule_context: bool,
     *     note: string
     * }
     */
    public function timezoneChangeImpactPreview(string $proposedTimezone, ?CarbonInterface $now = null): array
    {
        $this->assertValidTimezone($proposedTimezone);
        $now = $now ?? $this->now();
        $current = $this->timezone();

        $futureCount = ScheduledVisit::query()
            ->where('status', ScheduledVisitStatus::Scheduled)
            ->whereDate('service_date', '>=', $this->today($now))
            ->count();

        return [
            'current_timezone' => $current,
            'proposed_timezone' => $proposedTimezone,
            'current_today' => $now->toImmutable()->timezone($current)->toDateString(),
            'proposed_today' => $now->toImmutable()->timezone($proposedTimezone)->toDateString(),
            'future_scheduled_visits' => $futureCount,
            'preserves_schedule_context' => true,
            'note' => 'Existing future Scheduled Visits keep the schedule timezone stamped at creation. Historical UTC timestamps are not rewritten. New records after the effective point use the new Agency Operational Timezone.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentSettingChanges(int $limit = 20): array
    {
        return OrganizationSettingChangeEvent::query()
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (OrganizationSettingChangeEvent $event): array {
                $key = $event->setting_key instanceof OrganizationSettingChangeKey
                    ? $event->setting_key
                    : OrganizationSettingChangeKey::tryFrom((string) $event->setting_key);

                return [
                    'id' => $event->id,
                    'setting_key' => $key?->value ?? (string) $event->setting_key,
                    'setting_label' => $key?->label() ?? (string) $event->setting_key,
                    'summary' => $event->summary,
                    'previous_value' => $event->previous_value,
                    'new_value' => $event->new_value,
                    'reason' => $event->reason,
                    'effective_at' => $event->effective_at?->utc()->toIso8601String(),
                    'effective_at_label' => $event->effective_at !== null
                        ? $this->formatDateTime($event->effective_at)
                        : null,
                    'actor_name' => $event->actor?->name,
                    'created_at' => $event->created_at?->utc()->toIso8601String(),
                    'created_at_label' => $event->created_at !== null
                        ? $this->formatDateTime($event->created_at)
                        : null,
                    'meta' => $event->meta,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordSettingChange(
        OrganizationSettingChangeKey $key,
        ?string $previous,
        ?string $next,
        User $actor,
        ?string $reason = null,
        ?CarbonInterface $effectiveAt = null,
        array $meta = [],
    ): void {
        if ((string) $previous === (string) $next) {
            return;
        }

        OrganizationSettingChangeEvent::query()->create([
            'setting_key' => $key,
            'summary' => $key->label().' updated.',
            'previous_value' => $previous,
            'new_value' => $next,
            'reason' => $reason,
            'effective_at' => $effectiveAt?->utc(),
            'actor_user_id' => $actor->id,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    public function storeLogo(UploadedFile $file): void
    {
        $settings = $this->current();
        $this->deleteLogoFile($settings->logo_path);
        $path = $file->store('agency-logos', 'local');
        $settings->update(['logo_path' => $path]);
        $this->resolved = $settings->fresh() ?? $settings;
    }

    public function removeLogo(): void
    {
        $settings = $this->current();
        $this->deleteLogoFile($settings->logo_path);
        $settings->update(['logo_path' => null]);
        $this->resolved = $settings->fresh() ?? $settings;
    }

    public function logoUrl(): ?string
    {
        if (! filled($this->current()->logo_path)) {
            return null;
        }

        return route('organization.logo', [
            'v' => $this->current()->updated_at?->timestamp,
        ]);
    }

    public function logoDataUri(): ?string
    {
        $path = $this->current()->logo_path;

        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
        $contents = Storage::disk('local')->get($path);

        if ($contents === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public function streamLogo(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = $this->current()->logo_path;
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function deleteLogoFile(?string $path): void
    {
        if (filled($path) && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function timezone(): string
    {
        $this->applyDuePendingTimezone();

        $timezone = $this->current()->timezone;

        if (! $this->isValidTimezone($timezone)) {
            return 'UTC';
        }

        return $timezone;
    }

    /**
     * Official Agency Name — formal/official identity (MDM 11-03).
     */
    public function officialAgencyName(): string
    {
        $name = trim($this->current()->organization_name);

        return $name !== '' ? $name : 'Magic Data Management';
    }

    /**
     * Display Name with Official Agency Name fallback (MDM 11-03).
     * Used for lighter UI contexts such as shell/header.
     */
    public function agencyName(): string
    {
        $display = trim((string) $this->current()->display_name);

        return $display !== '' ? $display : $this->officialAgencyName();
    }

    /**
     * Configured agency location used for weather and display. Not device GPS.
     *
     * @return array{
     *     address_line_1: string,
     *     address_line_2: string,
     *     city: string,
     *     state: string,
     *     postal_code: string,
     *     country: string,
     *     label: string
     * }
     */
    public function agencyLocation(): array
    {
        $settings = $this->current();
        $line1 = trim((string) $settings->address_line_1);
        $line2 = trim((string) $settings->address_line_2);
        $city = trim((string) $settings->city);
        $state = trim((string) $settings->state);
        $postal = trim((string) $settings->postal_code);
        $country = trim((string) ($settings->country ?: 'United States'));

        $label = match (true) {
            $city !== '' && $state !== '' => $city.', '.$state,
            $city !== '' => $city,
            $state !== '' => $state,
            default => '',
        };

        return [
            'address_line_1' => $line1,
            'address_line_2' => $line2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal,
            'country' => $country,
            'label' => $label,
        ];
    }

    /**
     * Dashboard greeting for the organization-local hour.
     *
     * 05:00–11:59 Good morning
     * 12:00–16:59 Good afternoon
     * 17:00–04:59 Good evening
     */
    public function greeting(?CarbonInterface $now = null): string
    {
        $hour = (int) $this->localNow($now)->format('G');

        if ($hour >= 5 && $hour < 12) {
            return 'Good morning';
        }

        if ($hour >= 12 && $hour < 17) {
            return 'Good afternoon';
        }

        return 'Good evening';
    }

    /**
     * Top-bar sun/moon indicator for organization-local time.
     *
     * Sun 06:00–17:59, Moon 18:00–05:59.
     */
    public function isDaytime(?CarbonInterface $now = null): bool
    {
        $hour = (int) $this->localNow($now)->format('G');

        return $hour >= 6 && $hour < 18;
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
    public function at(string $date, string $time, ?string $timezone = null): CarbonInterface
    {
        $tz = $timezone ?? $this->timezone();
        $this->assertValidTimezone($tz);

        return Carbon::parse($date.' '.$this->normalizedTime($time), $tz)->toImmutable();
    }

    public function credentialExpiringSoonDays(): int
    {
        return $this->current()->credential_expiring_soon_days;
    }

    public function incidentAcknowledgmentMinutes(): int
    {
        $minutes = (int) $this->current()->incident_acknowledgment_minutes;

        return $minutes > 0
            ? $minutes
            : (int) config('mdm.incident.acknowledgment_minutes_default', 60);
    }

    /**
     * MDM 04-29: Agency-configured urgency threshold, in hours before scheduled start.
     */
    public function staffingNeededUrgencyHours(): int
    {
        $hours = (int) ($this->current()->staffing_needed_urgency_hours ?? 0);

        return $hours > 0
            ? $hours
            : max(1, (int) config('mdm.staffing.urgency_hours_default', 24));
    }

    public function formatDate(CarbonInterface $value): string
    {
        $local = $value->toImmutable()->timezone($this->timezone());

        return $local->format($this->current()->date_format->value);
    }

    /**
     * Format a stored calendar date without shifting it through the operational timezone.
     */
    public function formatCalendarDate(CarbonInterface $value): string
    {
        return Carbon::parse($value->toDateString())->format($this->current()->date_format->value);
    }

    public function formatTime(CarbonInterface $value): string
    {
        $local = $this->toLocal($value);

        return $this->current()->time_format === TimeFormat::TwelveHour
            ? $local->format('g:i A')
            : $local->format('H:i');
    }

    public function formatDateTime(CarbonInterface $value): string
    {
        return $this->formatDate($value).' '.$this->formatTime($value);
    }

    public function datetimeLocalValue(?CarbonInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->toLocal($value)->format('Y-m-d\TH:i');
    }

    public function parseLocalDateTime(?string $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $value);

        return Carbon::parse($normalized, $this->timezone())->utc()->toImmutable();
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
        $location = $this->agencyLocation();

        return [
            'organization_name' => $this->agencyName(),
            'official_organization_name' => $this->officialAgencyName(),
            'display_name' => trim((string) $settings->display_name) ?: null,
            'timezone' => $this->timezone(),
            'timezone_label' => $this->timezoneLabel($this->timezone()),
            'date_format' => $settings->date_format->value,
            'time_format' => $settings->time_format->value,
            'first_day_of_week' => $settings->first_day_of_week,
            'now' => $this->now()->utc()->toIso8601String(),
            'city' => $location['city'],
            'state' => $location['state'],
            'postal_code' => $location['postal_code'],
            'country' => $location['country'],
            'location_label' => $location['label'],
            'logo_url' => $this->logoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingTimezoneChangePayload(): ?array
    {
        $settings = $this->current();

        if ($settings->pending_timezone === null || $settings->pending_timezone_effective_at === null) {
            return null;
        }

        return [
            'timezone' => $settings->pending_timezone,
            'timezone_label' => $this->timezoneLabel((string) $settings->pending_timezone),
            'effective_at' => $settings->pending_timezone_effective_at->utc()->toIso8601String(),
            'effective_at_local' => $this->datetimeLocalValue($settings->pending_timezone_effective_at),
            'effective_at_label' => $this->formatDateTime($settings->pending_timezone_effective_at),
            'reason' => $settings->pending_timezone_reason,
            'scheduled_at_label' => $settings->pending_timezone_scheduled_at !== null
                ? $this->formatDateTime($settings->pending_timezone_scheduled_at)
                : null,
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

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MigrateScheduleTimezoneRequest;
use App\Http\Requests\Settings\UpdateOrganizationSettingsRequest;
use App\Services\ScheduleTimezoneMigrationService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private ScheduleTimezoneMigrationService $scheduleMigration,
    ) {}

    public function edit(): Response
    {
        $this->authorize('update', $this->settings->current());

        $current = $this->settings->current();
        $timezone = $this->settings->timezone();

        return Inertia::render('settings/general', [
            'settings' => [
                'organization_name' => $current->organization_name,
                'display_name' => $current->display_name,
                'address_line_1' => $current->address_line_1,
                'address_line_2' => $current->address_line_2,
                'city' => $current->city,
                'state' => $current->state,
                'postal_code' => $current->postal_code,
                'country' => $current->country ?: 'United States',
                'main_phone' => $current->main_phone,
                'main_email' => $current->main_email,
                'website' => $current->website,
                'timezone' => $timezone,
                'timezone_label' => $this->settings->timezoneLabel($timezone),
                'date_format' => $current->date_format->value,
                'time_format' => $current->time_format->value,
                'first_day_of_week' => $current->first_day_of_week,
                'credential_expiring_soon_days' => $this->settings->credentialExpiringSoonDays(),
                'incident_acknowledgment_minutes' => $this->settings->incidentAcknowledgmentMinutes(),
                'staffing_needed_urgency_hours' => $this->settings->staffingNeededUrgencyHours(),
                'logo_url' => $this->settings->logoUrl(),
                'has_logo' => filled($current->logo_path),
            ],
            'pendingTimezoneChange' => $this->settings->pendingTimezoneChangePayload(),
            'timezoneImpactPreview' => $this->settings->timezoneChangeImpactPreview($timezone),
            'scheduleMigrationPreview' => $this->scheduleMigration->preview($timezone),
            'settingChangeHistory' => $this->settings->recentSettingChanges(25),
            'timezoneOptions' => $this->settings->timezoneOptions(),
            'dateFormatOptions' => $this->settings->dateFormatOptions(),
            'timeFormatOptions' => $this->settings->timeFormatOptions(),
            'firstDayOfWeekOptions' => $this->settings->firstDayOfWeekOptions(),
        ]);
    }

    public function migrateSchedules(MigrateScheduleTimezoneRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $effectiveAt = filled($request->input('migration_effective_at'))
            ? Carbon::parse(
                str_replace('T', ' ', (string) $request->input('migration_effective_at')),
                $this->settings->timezone(),
            )->utc()->toImmutable()
            : $this->settings->now();

        $result = $this->scheduleMigration->migrate(
            $actor,
            (string) $request->validated('target_timezone'),
            (string) $request->validated('schedule_migration_reason'),
            $request->boolean('confirm_schedule_migration'),
            $effectiveAt,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(
                'Migrated :count future schedule(s) to :timezone. :conflicts conflict(s) left unchanged.',
                [
                    'count' => $result['migrated_count'],
                    'timezone' => $result['target_timezone'],
                    'conflicts' => $result['skipped_conflict_count'],
                ],
            ),
        ]);

        return to_route('settings.general.edit');
    }

    public function update(UpdateOrganizationSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        if ($request->boolean('cancel_pending_timezone')) {
            $this->settings->cancelPendingTimezoneChange($actor);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Pending timezone change cancelled.')]);

            return to_route('settings.general.edit');
        }

        $requestedTimezone = isset($data['timezone']) ? trim((string) $data['timezone']) : '';
        $currentTimezone = $this->settings->timezone();
        $scheduleTimezoneChange = $requestedTimezone !== '' && $requestedTimezone !== $currentTimezone;

        unset($data['timezone']);

        $this->settings->updateOrganization($data);

        if ($request->boolean('remove_logo')) {
            $this->settings->removeLogo();
        } elseif ($request->file('logo')) {
            $this->settings->storeLogo($request->file('logo'));
        }

        if ($scheduleTimezoneChange) {
            $effectiveLocal = (string) $request->input('timezone_effective_at');
            $effectiveAt = Carbon::parse(
                str_replace('T', ' ', $effectiveLocal),
                $currentTimezone,
            )->utc()->toImmutable();

            $preview = $this->settings->timezoneChangeImpactPreview($requestedTimezone);

            $this->settings->scheduleTimezoneChange(
                $actor,
                $requestedTimezone,
                $effectiveAt,
                (string) $request->input('timezone_change_reason', ''),
                $request->boolean('confirm_timezone_change'),
                $preview,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $effectiveAt->lte($this->settings->now())
                    ? __('Agency Operational Timezone updated. Historical timestamps were not rewritten.')
                    : __('Agency Operational Timezone change scheduled. The current timezone remains authoritative until the effective time.'),
            ]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization settings saved.')]);
        }

        return to_route('settings.general.edit');
    }
}

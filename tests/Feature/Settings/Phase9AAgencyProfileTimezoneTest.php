<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\OrganizationSettingChangeKey;
use App\Enums\TimeFormat;
use App\Models\Employee;
use App\Models\OrganizationSetting;
use App\Models\OrganizationSettingChangeEvent;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingsService;
use App\Services\VisitClockInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase9AAgencyProfileTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Ultimate Care Supported Living',
            'display_name' => 'Ultimate Care',
            'address_line_1' => '100 Care Way',
            'address_line_2' => 'Suite 2',
            'city' => 'Albany',
            'state' => 'NY',
            'postal_code' => '12207',
            'country' => 'United States',
            'main_phone' => '518-555-0100',
            'main_email' => 'ops@ultimatecare.example',
            'website' => 'https://ultimatecare.example',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
            'incident_acknowledgment_minutes' => 60,
            'staffing_needed_urgency_hours' => 24,
            'confirm_official_name_change' => '1',
            'official_name_change_reason' => 'Agency profile completion',
        ], $overrides);
    }

    public function test_admin_can_update_full_agency_profile_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('Ultimate Care Supported Living', $settings->organization_name);
        $this->assertSame('Ultimate Care', $settings->display_name);
        $this->assertSame('100 Care Way', $settings->address_line_1);
        $this->assertSame('Suite 2', $settings->address_line_2);
        $this->assertSame('Albany', $settings->city);
        $this->assertSame('NY', $settings->state);
        $this->assertSame('12207', $settings->postal_code);
        $this->assertSame('United States', $settings->country);
        $this->assertSame('518-555-0100', $settings->main_phone);
        $this->assertSame('ops@ultimatecare.example', $settings->main_email);
        $this->assertSame('https://ultimatecare.example', $settings->website);

        $this->assertSame('Ultimate Care', app(SettingsService::class)->agencyName());
        $this->assertSame('Ultimate Care Supported Living', app(SettingsService::class)->officialAgencyName());
    }

    public function test_non_admin_cannot_update_agency_profile(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        $this->actingAs($supervisor)
            ->patch(route('settings.general.update'), $this->profilePayload([
                'organization_name' => 'Should Not Save',
            ]))
            ->assertForbidden();

        $this->assertNotSame('Should Not Save', OrganizationSetting::query()->value('organization_name'));
    }

    public function test_official_name_change_requires_reason_confirmation_and_audits(): void
    {
        $admin = User::factory()->admin()->create();
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Harbor Supports',
        ]);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'organization_name' => 'Harbor Supports LLC',
                'confirm_official_name_change' => '0',
                'official_name_change_reason' => '',
            ]))
            ->assertSessionHasErrors([
                'confirm_official_name_change',
                'official_name_change_reason',
            ]);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'organization_name' => 'Harbor Supports LLC',
                'confirm_official_name_change' => '1',
                'official_name_change_reason' => 'Legal entity rename',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $event = OrganizationSettingChangeEvent::query()
            ->where('setting_key', OrganizationSettingChangeKey::OfficialAgencyName)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Harbor Supports', $event->previous_value);
        $this->assertSame('Harbor Supports LLC', $event->new_value);
        $this->assertSame('Legal entity rename', $event->reason);
        $this->assertSame($admin->id, $event->actor_user_id);
    }

    public function test_timezone_change_requires_governance_fields_and_stays_pending_until_effective(): void
    {
        Carbon::setTestNow('2026-10-21 15:00:00');
        $admin = User::factory()->admin()->create();
        app(SettingsService::class)->updateOrganization(['timezone' => 'America/New_York']);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'timezone' => 'America/Chicago',
                'confirm_timezone_change' => '0',
                'timezone_change_reason' => '',
                'timezone_effective_at' => '',
            ]))
            ->assertSessionHasErrors([
                'confirm_timezone_change',
                'timezone_change_reason',
                'timezone_effective_at',
            ]);

        $this->assertSame('America/New_York', app(SettingsService::class)->timezone());

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->patch(route('settings.general.update'), $this->profilePayload([
                'timezone' => 'America/Chicago',
                'timezone_effective_at' => '2026-10-22T09:00',
                'timezone_change_reason' => 'Agency relocation',
                'confirm_timezone_change' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('America/New_York', $settings->timezone);
        $this->assertSame('America/Chicago', $settings->pending_timezone);
        $this->assertSame('Agency relocation', $settings->pending_timezone_reason);
        $this->assertSame('America/New_York', app(SettingsService::class)->timezone());
        $this->assertSame('2026-10-21', app(SettingsService::class)->today());

        $event = OrganizationSettingChangeEvent::query()
            ->where('setting_key', OrganizationSettingChangeKey::AgencyOperationalTimezone)
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('America/New_York', $event->previous_value);
        $this->assertSame('America/Chicago', $event->new_value);
        $this->assertSame('Agency relocation', $event->reason);
        $this->assertNotNull($event->effective_at);

        Carbon::setTestNow('2026-10-22 14:00:00'); // 09:00 Chicago = 14:00 UTC during CDT
        app()->forgetInstance(SettingsService::class);
        $this->assertSame('America/Chicago', app(SettingsService::class)->timezone());
        $this->assertNull(OrganizationSetting::query()->value('pending_timezone'));
    }

    public function test_timezone_change_does_not_rewrite_historical_visit_timestamps_or_reschedule_stamped_visits(): void
    {
        Carbon::setTestNow('2026-03-08 17:00:00'); // US spring-forward weekend context
        $settings = app(SettingsService::class);
        $settings->updateOrganization(['timezone' => 'America/New_York']);

        $dsp = Employee::factory()->dsp()->create();
        $scheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-03-10',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
        ]);

        $this->assertSame('America/New_York', $scheduled->scheduleTimezone());
        $originalStartUtc = $scheduled->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s');
        $this->assertSame('2026-03-10 11:00:00', $originalStartUtc); // EDT

        Carbon::setTestNow('2026-03-09 12:00:00');
        $visit = app(VisitClockInService::class)->clockIn(
            $dsp->user()->firstOrFail(),
            ScheduledVisit::factory()->forDsp($dsp)->create([
                'service_date' => '2026-03-09',
                'starts_at' => '07:00:00',
                'ends_at' => '15:00:00',
                'schedule_timezone' => 'America/New_York',
            ]),
            [
                'location_method' => 'gps_unavailable',
                'location_status' => 'denied',
                'gps_fallback_reason' => 'permission_denied',
            ],
        );
        $clockedInUtc = $visit->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('settings.general.update'), $this->profilePayload([
                'timezone' => 'America/Chicago',
                'timezone_effective_at' => '2026-03-09T08:00',
                'timezone_change_reason' => 'Ops realignment',
                'confirm_timezone_change' => '1',
            ]))
            ->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-03-09 14:00:00');
        app()->forgetInstance(SettingsService::class);
        $this->assertSame('America/Chicago', app(SettingsService::class)->timezone());

        $scheduled->refresh();
        $this->assertSame('America/New_York', $scheduled->schedule_timezone);
        $this->assertSame(
            $originalStartUtc,
            $scheduled->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            $clockedInUtc,
            Visit::query()->findOrFail($visit->id)->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s'),
        );
    }

    public function test_dst_wall_clock_uses_iana_rules_for_stamped_schedule_timezone(): void
    {
        app(SettingsService::class)->updateOrganization(['timezone' => 'America/New_York']);

        $before = ScheduledVisit::factory()->create([
            'service_date' => '2026-03-07',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'schedule_timezone' => 'America/New_York',
        ]);
        $after = ScheduledVisit::factory()->create([
            'service_date' => '2026-03-09',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'schedule_timezone' => 'America/New_York',
        ]);

        // EST before spring-forward; EDT after.
        $this->assertSame('2026-03-07 14:00:00', $before->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-09 13:00:00', $after->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s'));

        app(SettingsService::class)->updateOrganization(['timezone' => 'America/Los_Angeles']);
        app()->forgetInstance(SettingsService::class);

        $before->refresh();
        $after->refresh();
        $this->assertSame('2026-03-07 14:00:00', $before->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-09 13:00:00', $after->startsAtOn()->timezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_high_risk_operational_thresholds_remain_admin_configurable_with_audit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('settings.general.update'), $this->profilePayload([
                'incident_acknowledgment_minutes' => 90,
                'staffing_needed_urgency_hours' => 48,
                'credential_expiring_soon_days' => 21,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(90, app(SettingsService::class)->incidentAcknowledgmentMinutes());
        $this->assertSame(48, app(SettingsService::class)->staffingNeededUrgencyHours());
        $this->assertSame(21, app(SettingsService::class)->credentialExpiringSoonDays());

        $this->assertTrue(
            OrganizationSettingChangeEvent::query()
                ->where('setting_key', OrganizationSettingChangeKey::IncidentAcknowledgmentMinutes)
                ->where('new_value', '90')
                ->exists(),
        );
        $this->assertTrue(
            OrganizationSettingChangeEvent::query()
                ->where('setting_key', OrganizationSettingChangeKey::StaffingNeededUrgencyHours)
                ->where('new_value', '48')
                ->exists(),
        );
    }
}

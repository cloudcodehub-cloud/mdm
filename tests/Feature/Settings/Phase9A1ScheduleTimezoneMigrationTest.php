<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\OrganizationSettingChangeKey;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitAssignmentKind;
use App\Models\Employee;
use App\Models\OrganizationSettingChangeEvent;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAssignment;
use App\Models\ScheduledVisitSeries;
use App\Models\User;
use App\Models\Visit;
use App\Services\ScheduleTimezoneMigrationService;
use App\Services\SettingsService;
use App\Services\VisitClockInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase9A1ScheduleTimezoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'America/Chicago',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agency_timezone_change_does_not_auto_migrate_future_schedules(): void
    {
        Carbon::setTestNow('2026-10-21 15:00:00');
        $admin = User::factory()->admin()->create();

        $visit = ScheduledVisit::factory()->create([
            'service_date' => '2026-10-25',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $originalUtc = $visit->startsAtOn()->utc()->format('Y-m-d H:i:s');

        $this->actingAs($admin)
            ->patch(route('settings.general.update'), [
                'organization_name' => 'MDM - Magic Data Management',
                'timezone' => 'America/Denver',
                'timezone_effective_at' => '2026-10-21T10:00',
                'timezone_change_reason' => 'Agency TZ only',
                'confirm_timezone_change' => '1',
                'date_format' => DateFormat::MonthDayYear->value,
                'time_format' => TimeFormat::TwelveHour->value,
                'first_day_of_week' => 0,
                'credential_expiring_soon_days' => 30,
                'incident_acknowledgment_minutes' => 60,
                'staffing_needed_urgency_hours' => 24,
            ])
            ->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame('America/New_York', $visit->schedule_timezone);
        $this->assertSame($originalUtc, $visit->startsAtOn()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/Denver', app(SettingsService::class)->timezone());
    }

    public function test_admin_migration_preserves_local_intent_recalculates_utc_and_audits(): void
    {
        Carbon::setTestNow('2026-10-21 15:00:00');
        $admin = User::factory()->admin()->create();

        $series = ScheduledVisitSeries::factory()->create();
        $visit = ScheduledVisit::factory()->create([
            'service_date' => '2026-10-25',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'series_id' => $series->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $assignment = ScheduledVisitAssignment::factory()->create([
            'scheduled_visit_id' => $visit->id,
            'employee_id' => $visit->employee_id,
            'assigned_by_user_id' => $admin->id,
            'kind' => VisitAssignmentKind::Assigned,
            'assigned_at' => now(),
        ]);

        $beforeUtc = $visit->startsAtOn()->utc()->format('Y-m-d H:i:s');
        $this->assertSame('2026-10-25 11:00:00', $beforeUtc); // EDT

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->post(route('settings.general.migrate-schedules'), [
                'target_timezone' => 'America/Chicago',
                'schedule_migration_reason' => 'Align future schedules with Agency TZ',
                'confirm_schedule_migration' => '1',
                'migration_effective_at' => '2026-10-21T10:00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.general.edit'));

        $visit->refresh();
        $this->assertSame('America/Chicago', $visit->schedule_timezone);
        $this->assertSame('07:00:00', $visit->starts_at);
        $this->assertSame('15:00:00', $visit->ends_at);
        $this->assertSame($series->id, $visit->series_id);
        $this->assertSame('2026-10-25 12:00:00', $visit->startsAtOn()->utc()->format('Y-m-d H:i:s')); // CDT

        $this->assertDatabaseHas('scheduled_visit_assignments', [
            'id' => $assignment->id,
            'scheduled_visit_id' => $visit->id,
            'kind' => VisitAssignmentKind::Assigned->value,
            'assigned_by_user_id' => $admin->id,
        ]);

        $event = OrganizationSettingChangeEvent::query()
            ->where('setting_key', OrganizationSettingChangeKey::ScheduleTimezoneMigration)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Align future schedules with Agency TZ', $event->reason);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertNotNull($event->effective_at);
        $this->assertSame(1, $event->meta['migrated_count'] ?? null);
        $this->assertTrue($event->meta['preserves_local_service_time_intent'] ?? false);
        $this->assertTrue($event->meta['series_and_assignment_history_preserved'] ?? false);
    }

    public function test_non_admin_cannot_migrate_schedules(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        ScheduledVisit::factory()->create([
            'service_date' => '2026-10-25',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($supervisor)
            ->post(route('settings.general.migrate-schedules'), [
                'target_timezone' => 'America/Chicago',
                'schedule_migration_reason' => 'Nope',
                'confirm_schedule_migration' => '1',
            ])
            ->assertForbidden();
    }

    public function test_migration_requires_reason_and_confirmation(): void
    {
        $admin = User::factory()->admin()->create();

        ScheduledVisit::factory()->create([
            'service_date' => '2026-10-25',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('settings.general.edit'))
            ->post(route('settings.general.migrate-schedules'), [
                'target_timezone' => 'America/Chicago',
            ])
            ->assertSessionHasErrors([
                'schedule_migration_reason',
                'confirm_schedule_migration',
            ]);
    }

    public function test_dst_gap_and_fold_conflicts_are_skipped_without_invented_conversion(): void
    {
        Carbon::setTestNow('2026-02-01 12:00:00');
        app(SettingsService::class)->updateOrganization(['timezone' => 'America/Chicago']);

        $gap = ScheduledVisit::factory()->create([
            'service_date' => '2026-03-08',
            'starts_at' => '02:30:00',
            'ends_at' => '10:00:00',
            'schedule_timezone' => 'UTC',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $fold = ScheduledVisit::factory()->create([
            'service_date' => '2026-11-01',
            'starts_at' => '01:30:00',
            'ends_at' => '09:00:00',
            'schedule_timezone' => 'UTC',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $safe = ScheduledVisit::factory()->create([
            'service_date' => '2026-03-10',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'schedule_timezone' => 'UTC',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $preview = app(ScheduleTimezoneMigrationService::class)->preview('America/New_York');
        $this->assertSame(3, $preview['eligible_count']);
        $this->assertSame(1, $preview['migratable_count']);
        $this->assertSame(2, $preview['conflict_count']);

        $admin = User::factory()->admin()->create();
        $result = app(ScheduleTimezoneMigrationService::class)->migrate(
            $admin,
            'America/New_York',
            'DST-safe migration',
            true,
        );

        $this->assertSame(1, $result['migrated_count']);
        $this->assertSame(2, $result['skipped_conflict_count']);

        $this->assertSame('UTC', $gap->fresh()->schedule_timezone);
        $this->assertSame('UTC', $fold->fresh()->schedule_timezone);
        $this->assertSame('America/New_York', $safe->fresh()->schedule_timezone);
        $this->assertSame(
            '2026-03-10 13:00:00',
            $safe->fresh()->startsAtOn()->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_completed_visits_evv_and_historical_utc_are_never_migrated(): void
    {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $dsp = Employee::factory()->dsp()->create();

        $future = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-10-25',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $historicalScheduled = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-10-20',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        $visit = app(VisitClockInService::class)->clockIn(
            $dsp->user()->firstOrFail(),
            $historicalScheduled,
            [
                'location_method' => 'gps_unavailable',
                'location_status' => 'denied',
                'gps_fallback_reason' => 'permission_denied',
            ],
        );
        $clockedInUtc = $visit->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s');
        $historicalStartUtc = $historicalScheduled->startsAtOn()->utc()->format('Y-m-d H:i:s');

        $completed = ScheduledVisit::factory()->forDsp($dsp)->create([
            'service_date' => '2026-10-26',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $completedStartUtc = $completed->startsAtOn()->utc()->format('Y-m-d H:i:s');

        $admin = User::factory()->admin()->create();
        app(ScheduleTimezoneMigrationService::class)->migrate(
            $admin,
            'America/Chicago',
            'Migrate future only',
            true,
        );

        $this->assertSame('America/Chicago', $future->fresh()->schedule_timezone);
        $this->assertSame('America/New_York', $historicalScheduled->fresh()->schedule_timezone);
        $this->assertSame('America/New_York', $completed->fresh()->schedule_timezone);
        $this->assertSame(
            $historicalStartUtc,
            $historicalScheduled->fresh()->startsAtOn()->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            $completedStartUtc,
            $completed->fresh()->startsAtOn()->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            $clockedInUtc,
            Visit::query()->findOrFail($visit->id)->clocked_in_at->timezone('UTC')->format('Y-m-d H:i:s'),
        );
    }

    public function test_recurring_series_members_migrate_individually_without_rewriting_series_exceptions(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $series = ScheduledVisitSeries::factory()->create();

        $memberA = ScheduledVisit::factory()->create([
            'service_date' => '2026-10-22',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'schedule_timezone' => 'America/New_York',
            'series_id' => $series->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $memberB = ScheduledVisit::factory()->create([
            'service_date' => '2026-10-23',
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'schedule_timezone' => 'America/New_York',
            'series_id' => $series->id,
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'series exception note preserved',
        ]);

        $admin = User::factory()->admin()->create();
        app(ScheduleTimezoneMigrationService::class)->migrate(
            $admin,
            'America/Chicago',
            'Series-aware migration',
            true,
        );

        $memberA->refresh();
        $memberB->refresh();
        $this->assertSame('America/Chicago', $memberA->schedule_timezone);
        $this->assertSame('America/Chicago', $memberB->schedule_timezone);
        $this->assertSame($series->id, $memberA->series_id);
        $this->assertSame($series->id, $memberB->series_id);
        $this->assertSame('series exception note preserved', $memberB->notes);
        $this->assertSame('08:00:00', $memberA->starts_at);
        $this->assertSame('09:00:00', $memberB->starts_at);
    }

    public function test_pending_agency_timezone_still_applies_when_due_without_migrating_stamps(): void
    {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $admin = User::factory()->admin()->create();
        app(SettingsService::class)->updateOrganization(['timezone' => 'America/New_York']);

        $visit = ScheduledVisit::factory()->create([
            'service_date' => '2026-10-30',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'schedule_timezone' => 'America/New_York',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $originalUtc = $visit->startsAtOn()->utc()->format('Y-m-d H:i:s');

        app(SettingsService::class)->scheduleTimezoneChange(
            $admin,
            'America/Chicago',
            Carbon::parse('2026-10-22 14:00:00', 'UTC'),
            'Deferred agency TZ',
            true,
        );

        $this->assertSame('America/New_York', app(SettingsService::class)->timezone());

        Carbon::setTestNow('2026-10-22 14:00:00');
        app()->forgetInstance(SettingsService::class);
        $this->assertSame('America/Chicago', app(SettingsService::class)->timezone());

        $visit->refresh();
        $this->assertSame('America/New_York', $visit->schedule_timezone);
        $this->assertSame($originalUtc, $visit->startsAtOn()->utc()->format('Y-m-d H:i:s'));
    }
}

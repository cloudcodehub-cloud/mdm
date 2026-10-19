<?php

namespace Tests\Feature;

use App\Enums\DateFormat;
use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeTimeOff;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAvailabilityOverride;
use App\Models\ScheduledVisitSeries;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StaffingUrgencyService;
use App\Services\VisitAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 5B: MDM 04-29 configurable Staffing Needed urgency threshold (+ MDM 04-19 verification).
 */
class Phase5BStaffingUrgencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secondAdmin;

    private Employee $supervisor;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'UTC',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 30,
            'incident_acknowledgment_minutes' => 60,
            'staffing_needed_urgency_hours' => 24,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-20 09:00:00', 'UTC'));

        $this->admin = User::factory()->admin()->create();
        $this->secondAdmin = User::factory()->admin()->create();
        $this->supervisor = Employee::factory()->supervisor()->create();
        $this->client = Client::factory()->forSupervisor($this->supervisor)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function staffingNeeded(array $attributes = []): ScheduledVisit
    {
        return ScheduledVisit::factory()->forClient($this->client)->create([
            'employee_id' => null,
            'staffing_needed' => true,
            'supervisor_id' => $this->supervisor->id,
            'service_date' => '2026-10-25',
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            ...$attributes,
        ]);
    }

    private function urgencyNotifications(): \Illuminate\Database\Eloquent\Builder
    {
        return InAppNotification::query()->where('type', InAppNotificationType::StaffingUrgency->value);
    }

    private function urgency(): StaffingUrgencyService
    {
        return app(StaffingUrgencyService::class);
    }

    public function test_distant_future_staffing_needed_is_not_urgent_and_does_not_notify_admins(): void
    {
        $visit = $this->staffingNeeded(); // 2026-10-25 17:00, ~5.3 days away; threshold 24h

        $this->assertSame(StaffingUrgencyService::STATE_UPCOMING, $this->urgency()->stateFor($visit));
        $this->assertFalse($this->urgency()->isUrgent($visit));
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());
        $this->assertSame(0, $this->urgencyNotifications()->count());
        $this->assertNull($visit->fresh()->staffing_urgency_escalated_at);
    }

    public function test_within_threshold_is_urgent_admins_notified_once_and_not_duplicated(): void
    {
        $visit = $this->staffingNeeded([
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00', // 23h ahead of 2026-10-20 09:00 UTC
            'ends_at' => '10:00:00',
        ]);

        $this->assertSame(StaffingUrgencyService::STATE_URGENT, $this->urgency()->stateFor($visit));

        $this->assertSame(1, $this->urgency()->escalateDueUrgency());

        $this->assertSame(2, $this->urgencyNotifications()->count());
        foreach ([$this->admin, $this->secondAdmin] as $admin) {
            $notification = $this->urgencyNotifications()->where('user_id', $admin->id)->sole();
            $this->assertSame('Urgent Staffing Needed', $notification->title);
            $this->assertStringContainsString('24-hour', $notification->body);
            $this->assertStringContainsString($this->client->full_name, $notification->body);
        }
        // Supervisor owns the visit on the Ops board; no duplicate in-app notification.
        $this->assertSame(
            0,
            $this->urgencyNotifications()->where('user_id', $this->supervisor->user_id)->count(),
        );
        $this->assertNotNull($visit->fresh()->staffing_urgency_escalated_at);

        // Duplicate prevention: repeated dispatch and the console command do nothing more.
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());
        Carbon::setTestNow(Carbon::parse('2026-10-20 09:05:00', 'UTC'));
        $this->artisan('mdm:dispatch-staffing-urgency-escalations')->assertSuccessful();
        $this->assertSame(2, $this->urgencyNotifications()->count());
    }

    public function test_visit_at_or_past_start_is_overdue_and_escalates_with_overdue_title(): void
    {
        $visit = $this->staffingNeeded([
            'service_date' => '2026-10-20',
            'starts_at' => '07:00:00',
            'ends_at' => '09:30:00',
        ]);

        $this->assertSame(StaffingUrgencyService::STATE_OVERDUE, $this->urgency()->stateFor($visit));
        $this->assertSame('Overdue Staffing Needed', $this->urgency()->presentation($visit)['label']);

        $this->assertSame(1, $this->urgency()->escalateDueUrgency());
        $this->assertSame(
            'Overdue Staffing Needed',
            $this->urgencyNotifications()->where('user_id', $this->admin->id)->sole()->title,
        );
    }

    public function test_cancelled_and_assigned_visits_are_never_urgent(): void
    {
        $cancelled = $this->staffingNeeded([
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'status' => ScheduledVisitStatus::Cancelled,
        ]);
        $dsp = Employee::factory()->dsp()->forSupervisor($this->supervisor)->create();
        $assigned = ScheduledVisit::factory()->forClient($this->client)->forDsp($dsp)->create([
            'supervisor_id' => $this->supervisor->id,
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
        ]);

        $this->assertSame(StaffingUrgencyService::STATE_NONE, $this->urgency()->stateFor($cancelled));
        $this->assertSame(StaffingUrgencyService::STATE_NONE, $this->urgency()->stateFor($assigned));
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());
        $this->assertSame(0, $this->urgencyNotifications()->count());
    }

    public function test_staffing_the_visit_clears_escalation_and_urgency_and_new_release_can_escalate_again(): void
    {
        $visit = $this->staffingNeeded([
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
        ]);
        $dsp = Employee::factory()->dsp()->forSupervisor($this->supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($this->client)->create();

        $this->assertSame(1, $this->urgency()->escalateDueUrgency());
        $this->assertNotNull($visit->fresh()->staffing_urgency_escalated_at);

        $staffed = app(VisitAssignmentService::class)->reassign($visit->fresh(), $dsp, $this->admin, 'Filled by Supervisor');

        $this->assertNull($staffed->staffing_urgency_escalated_at);
        $this->assertFalse($staffed->staffing_needed);
        $this->assertSame(StaffingUrgencyService::STATE_NONE, $this->urgency()->stateFor($staffed));
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());

        $this->actingAs($this->admin)
            ->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.urgent_staffing_needed_visits', 0)
                ->where('operations.metrics', fn ($metrics) => collect($metrics)->pluck('value', 'key')->get('urgent_staffing_needed') === 0));

        // A later release is a fresh Staffing Needed episode and may escalate once more.
        Carbon::setTestNow(Carbon::parse('2026-10-20 09:01:00', 'UTC'));
        $released = app(VisitAssignmentService::class)->releaseToStaffingNeeded($staffed, $this->admin, 'Call-off');
        $this->assertNull($released->staffing_urgency_escalated_at);
        $this->assertSame(1, $this->urgency()->escalateDueUrgency());
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());
    }

    public function test_threshold_setting_and_agency_timezone_with_dst_are_respected(): void
    {
        $settings = app(SettingsService::class);
        $settings->updateOrganization(['timezone' => 'America/New_York']);

        // 09:00 Eastern on 2026-10-22 is 13:00 UTC (EDT). 24h threshold => urgent from 2026-10-21 13:00 UTC.
        $visit = $this->staffingNeeded([
            'service_date' => '2026-10-22',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);
        $this->assertSame('2026-10-22 13:00:00', $this->urgency()->scheduledStart($visit)->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(StaffingUrgencyService::STATE_UPCOMING, $this->urgency()->stateFor($visit));
        $this->assertSame(0, $this->urgency()->escalateDueUrgency());

        // Agency raises the threshold to 72h: the same visit is now urgent immediately.
        $settings->updateOrganization(['staffing_needed_urgency_hours' => 72]);
        $this->assertSame(72, $settings->staffingNeededUrgencyHours());
        $this->assertSame(StaffingUrgencyService::STATE_URGENT, $this->urgency()->stateFor($visit));

        // Lowering the threshold again removes urgency prospectively (no escalation recorded yet).
        $settings->updateOrganization(['staffing_needed_urgency_hours' => 24]);
        $this->assertSame(StaffingUrgencyService::STATE_UPCOMING, $this->urgency()->stateFor($visit));

        // DST: US fall-back is 2026-11-01. 09:00 Eastern that day is 14:00 UTC (EST, not EDT 13:00).
        $dst = $this->staffingNeeded([
            'service_date' => '2026-11-01',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);
        $this->assertSame('2026-11-01 14:00:00', $this->urgency()->scheduledStart($dst)->utc()->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-10-31 13:59:00', 'UTC'));
        $this->assertSame(StaffingUrgencyService::STATE_UPCOMING, $this->urgency()->stateFor($dst));
        Carbon::setTestNow(Carbon::parse('2026-10-31 14:00:00', 'UTC'));
        $this->assertSame(StaffingUrgencyService::STATE_URGENT, $this->urgency()->stateFor($dst));
    }

    public function test_operations_board_marks_urgent_and_overdue_for_supervisor_and_admin_urgent_queue(): void
    {
        $overdue = $this->staffingNeeded([
            'service_date' => '2026-10-20',
            'starts_at' => '07:00:00',
            'ends_at' => '09:30:00',
        ]);
        $urgent = $this->staffingNeeded([
            'service_date' => '2026-10-21',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
        ]);
        $distant = $this->staffingNeeded();
        $this->staffingNeeded([
            'service_date' => '2026-10-21',
            'starts_at' => '06:00:00',
            'ends_at' => '07:00:00',
            'status' => ScheduledVisitStatus::Cancelled,
        ]);

        // Supervisor: all three remain in the caseload Staffing Needed list, annotated by urgency.
        $this->actingAs($this->supervisor->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->has('operations.staffing_needed_visits', 3)
                ->where('operations.staffing_needed_visits', function ($rows) use ($overdue, $urgent, $distant): bool {
                    $byId = collect($rows)->keyBy('scheduled_visit_id');

                    return $byId[$overdue->id]['staffing_urgency'] === 'overdue'
                        && $byId[$overdue->id]['staffing_urgency_label'] === 'Overdue Staffing Needed'
                        && $byId[$urgent->id]['staffing_urgency'] === 'urgent'
                        && $byId[$urgent->id]['staffing_urgency_label'] === 'Urgent Staffing Needed'
                        && $byId[$distant->id]['staffing_urgency'] === 'upcoming'
                        && $byId[$distant->id]['staffing_urgency_label'] === null;
                })
                ->where('operations.staffing_urgency_threshold_hours', 24)
                ->has('operations.urgent_staffing_needed_visits', 2)
                ->where('operations.urgent_staffing_needed_visits.0.scheduled_visit_id', $overdue->id)
                ->where('operations.urgent_staffing_needed_visits.1.scheduled_visit_id', $urgent->id)
            );

        // Admin: agency-wide Staffing Needed (existing) + urgent queue and metric.
        $this->actingAs($this->admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.staffing_needed_visits', 3)
                ->has('operations.urgent_staffing_needed_visits', 2)
                ->where('operations.metrics', function ($metrics): bool {
                    $values = collect($metrics)->pluck('value', 'key');

                    return $values->get('urgent_staffing_needed') === 2
                        && $values->get('staffing_needed') === 3;
                })
            );

        // Caseload scoping holds: an unrelated Supervisor sees nothing urgent.
        $other = Employee::factory()->supervisor()->create();
        $this->actingAs($other->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.staffing_needed_visits', 0)
                ->has('operations.urgent_staffing_needed_visits', 0));
    }

    public function test_settings_threshold_change_flows_to_operations_board(): void
    {
        $this->staffingNeeded([
            'service_date' => '2026-10-23',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]); // ~72h away

        $supervisorUser = $this->supervisor->user()->firstOrFail();

        $this->actingAs($supervisorUser)->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('operations.urgent_staffing_needed_visits', 0));

        app(SettingsService::class)->updateOrganization(['staffing_needed_urgency_hours' => 96]);

        $this->actingAs($supervisorUser)->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.urgent_staffing_needed_visits', 1)
                ->where('operations.staffing_urgency_threshold_hours', 96));
    }

    /**
     * MDM 04-19 verification: unacknowledged near-term overrides are surfaced on the Ops board
     * (Phase 5A). The frozen text requires DSP notification + acknowledgment and Supervisor
     * surfacing — NOT a separate Supervisor in-app notification.
     */
    public function test_unacknowledged_override_is_on_ops_board_without_a_supervisor_notification(): void
    {
        $dsp = Employee::factory()->dsp()->forSupervisor($this->supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($this->client)->create();
        EmployeeTimeOff::factory()->create([
            'employee_id' => $dsp->id,
            'starts_on' => '2026-10-21',
            'ends_on' => '2026-10-21',
            'requested_by_user_id' => $dsp->user_id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $this->client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-10-21',
                'starts_at' => '17:00',
                'ends_at' => '19:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'override_reason' => 'Coverage emergency',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ScheduledVisitAvailabilityOverride::query()->count());
        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $dsp->user_id)
                ->where('type', InAppNotificationType::AvailabilityOverride->value)
                ->exists(),
        );
        // No Supervisor-directed override notification exists in the frozen text.
        $this->assertSame(
            0,
            InAppNotification::query()
                ->where('user_id', $this->supervisor->user_id)
                ->where('type', InAppNotificationType::AvailabilityOverride->value)
                ->count(),
        );

        $this->actingAs($this->supervisor->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.availability_overrides.unacknowledged', 1));
    }

    /**
     * MDM 04-19 verification: the override is Visit-specific and is never auto-applied to
     * other visits in a recurring series when the series is edited.
     */
    public function test_series_edit_does_not_auto_apply_override_to_other_series_visits(): void
    {
        $dsp = Employee::factory()->dsp()->forSupervisor($this->supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($this->client)->create();
        EmployeeTimeOff::factory()->create([
            'employee_id' => $dsp->id,
            'starts_on' => '2026-10-21',
            'ends_on' => '2026-10-28',
            'requested_by_user_id' => $dsp->user_id,
        ]);

        $series = ScheduledVisitSeries::factory()->create([
            'client_id' => $this->client->id,
            'employee_id' => $dsp->id,
        ]);
        $first = ScheduledVisit::factory()->forClient($this->client)->forDsp($dsp)->create([
            'series_id' => $series->id,
            'supervisor_id' => $this->supervisor->id,
            'service_date' => '2026-10-21',
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
        ]);
        $second = ScheduledVisit::factory()->forClient($this->client)->forDsp($dsp)->create([
            'series_id' => $series->id,
            'supervisor_id' => $this->supervisor->id,
            'service_date' => '2026-10-28',
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
        ]);

        $this->actingAs($this->admin)
            ->put(route('scheduled-visits.update', $first), [
                'client_id' => $this->client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-10-21',
                'starts_at' => '17:00',
                'ends_at' => '19:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'series_scope' => 'series',
                'notes' => 'Series-wide note',
                'override_reason' => 'Coverage emergency for this visit only',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Series-wide note', $second->fresh()->notes);
        $this->assertSame(
            1,
            ScheduledVisitAvailabilityOverride::query()->where('scheduled_visit_id', $first->id)->count(),
        );
        $this->assertSame(
            0,
            ScheduledVisitAvailabilityOverride::query()->where('scheduled_visit_id', $second->id)->count(),
        );
    }
}

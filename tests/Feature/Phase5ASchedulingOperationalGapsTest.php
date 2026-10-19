<?php

namespace Tests\Feature;

use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\DateFormat;
use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Enums\VisitAssignmentKind;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\ComplianceRequirement;
use App\Models\DspAvailabilityException;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTimeOff;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAvailabilityOverride;
use App\Models\User;
use App\Services\ComplianceStaffingService;
use App\Services\DspAvailabilityService;
use App\Services\SettingsService;
use App\Support\ClockMinutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 5A: MDM 04-19 unavailable-period override + Staffing Operations visibility.
 */
class Phase5ASchedulingOperationalGapsTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-10-21';

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
        ]);
        Carbon::setTestNow('2026-10-19 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Employee, 2: Employee, 3: Client}
     */
    private function scenario(): array
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        return [$admin, $supervisor, $dsp, $client];
    }

    private function approvedLeave(Employee $dsp, string $date = self::DATE): EmployeeTimeOff
    {
        return EmployeeTimeOff::factory()->create([
            'employee_id' => $dsp->id,
            'starts_on' => $date,
            'ends_on' => $date,
            'requested_by_user_id' => $dsp->user_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(Client $client, Employee $dsp, array $overrides = []): array
    {
        return [
            'client_id' => $client->id,
            'employee_id' => $dsp->id,
            'service_date' => self::DATE,
            'starts_at' => '17:00',
            'ends_at' => '19:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled->value,
            ...$overrides,
        ];
    }

    public function test_leave_blocks_scheduling_without_override_reason(): void
    {
        [$admin, , $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp))
            ->assertSessionHasErrors(['employee_id', 'availability_override_required', 'override_reason']);

        $this->assertSame(0, ScheduledVisit::query()->count());
        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());
    }

    public function test_admin_assigns_over_leave_with_reason_and_dsp_is_notified_with_audit_row(): void
    {
        [$admin, , $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Client has no other qualified caregiver that evening.',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $visit = ScheduledVisit::query()->where('employee_id', $dsp->id)->firstOrFail();
        $this->assertSame(ScheduledVisitStatus::Scheduled, $visit->status);

        $override = ScheduledVisitAvailabilityOverride::query()->firstOrFail();
        $this->assertSame($visit->id, $override->scheduled_visit_id);
        $this->assertSame($dsp->id, $override->employee_id);
        $this->assertSame('leave', $override->conflict_kind);
        $this->assertSame(DspAvailabilityService::LEAVE_CONFLICT_MESSAGE, $override->conflict_summary);
        $this->assertSame('Client has no other qualified caregiver that evening.', $override->override_reason);
        $this->assertSame($admin->id, $override->overridden_by_user_id);
        $this->assertNotNull($override->overridden_at);
        $this->assertNull($override->acknowledged_at);

        $notification = InAppNotification::query()
            ->where('user_id', $dsp->user_id)
            ->where('type', InAppNotificationType::AvailabilityOverride->value)
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Client has no other qualified caregiver', $notification->body);
        $this->assertStringContainsString($client->full_name, $notification->body);
    }

    public function test_in_caseload_supervisor_assigns_over_unavailable_exception_with_reason(): void
    {
        [, $supervisor, $dsp, $client] = $this->scenario();
        DspAvailabilityException::factory()->create([
            'employee_id' => $dsp->id,
            'exception_date' => self::DATE,
            'is_available' => false,
            'starts_at' => '16:00:00',
            'ends_at' => '23:59:00',
        ]);

        $supervisorUser = $supervisor->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp))
            ->assertSessionHasErrors(['availability_override_required', 'override_reason']);

        $this->actingAs($supervisorUser)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Urgent coverage; DSP agreed by phone.',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $override = ScheduledVisitAvailabilityOverride::query()->firstOrFail();
        $this->assertSame('unavailable', $override->conflict_kind);
        $this->assertSame($supervisorUser->id, $override->overridden_by_user_id);
    }

    public function test_replacement_over_leave_requires_reason_then_records_override_and_notifies(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($replacement)->forClient($client)->create();
        $this->approvedLeave($replacement);

        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => self::DATE,
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.replace', $visit), [
                'employee_id' => $replacement->id,
                'reason' => 'Call-off',
            ])
            ->assertSessionHasErrors(['availability_override_required']);

        $this->assertSame($dsp->id, $visit->fresh()->employee_id);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.replace', $visit), [
                'employee_id' => $replacement->id,
                'reason' => 'Call-off',
                'override_reason' => 'Only available replacement; leave is a half-day.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('scheduled-visits.show', $visit));

        $visit->refresh();
        $this->assertSame($replacement->id, $visit->employee_id);
        $this->assertTrue($visit->assignments()->where('kind', VisitAssignmentKind::Replacement)->exists());

        $override = ScheduledVisitAvailabilityOverride::query()->firstOrFail();
        $this->assertSame($replacement->id, $override->employee_id);
        $this->assertSame($visit->id, $override->scheduled_visit_id);
        $this->assertSame('Only available replacement; leave is a half-day.', $override->override_reason);
        $this->assertTrue(
            InAppNotification::query()
                ->where('user_id', $replacement->user_id)
                ->where('type', InAppNotificationType::AvailabilityOverride->value)
                ->exists()
        );
    }

    public function test_replacement_without_conflict_does_not_record_an_override(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($replacement)->forClient($client)->create();

        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => self::DATE,
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.replace', $visit), [
                'employee_id' => $replacement->id,
                'reason' => 'Call-off',
                'override_reason' => 'Not actually needed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());
    }

    public function test_double_booking_is_still_blocked_even_with_override_reason(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => self::DATE,
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'starts_at' => '18:00',
                'ends_at' => '20:00',
                'override_reason' => 'Please assign anyway.',
            ]))
            ->assertSessionHasErrors('service_date');

        $this->assertSame(1, ScheduledVisit::query()->count());
        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());

        // Replacement path: the candidate replacement already has an overlapping visit and leave.
        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($replacement)->forClient($client)->create();
        $this->approvedLeave($replacement);
        ScheduledVisit::factory()->forClient($client)->forDsp($replacement)->create([
            'service_date' => self::DATE,
            'starts_at' => '16:00:00',
            'ends_at' => '20:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        $target = ScheduledVisit::query()->where('employee_id', $dsp->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('scheduled-visits.replace', $target), [
                'employee_id' => $replacement->id,
                'reason' => 'Call-off',
                'override_reason' => 'Please assign anyway.',
            ])
            ->assertSessionHasErrors();

        $this->assertSame($dsp->id, $target->fresh()->employee_id);
        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());
    }

    public function test_availability_service_override_flag_skips_leave_but_never_overlap_or_inactive(): void
    {
        [, $supervisor, $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);
        $service = app(DspAvailabilityService::class);
        $window = ClockMinutes::window('17:00:00', '19:00:00');

        $this->assertSame(
            DspAvailabilityService::LEAVE_CONFLICT_MESSAGE,
            $service->hardBlockReason($dsp, self::DATE, $window),
        );
        $this->assertSame(
            DspAvailabilityService::LEAVE_CONFLICT_MESSAGE,
            $service->availabilityConflictReason($dsp, self::DATE, $window),
        );
        $this->assertNull($service->hardBlockReason($dsp, self::DATE, $window, null, true));

        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => self::DATE,
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->assertStringContainsString(
            'already has a scheduled visit',
            (string) $service->hardBlockReason($dsp, self::DATE, $window, null, true),
        );

        $inactive = Employee::factory()->dsp()->suspended()->create();
        $this->assertSame(
            'Only active DSP employees can be scheduled.',
            $service->hardBlockReason($inactive, self::DATE, $window, null, true),
        );
    }

    public function test_inactive_dsp_and_compliance_hard_stop_cannot_be_overridden(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);

        $inactive = Employee::factory()->dsp()->suspended()->forSupervisor($supervisor)->create();
        $this->approvedLeave($inactive);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $inactive, [
                'override_reason' => 'Assign anyway',
            ]))
            ->assertSessionHasErrors('employee_id');

        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01']);
        ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::Cpr)
            ->nonOverrideable()
            ->create();
        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::Cpr,
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-01',
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Assign anyway',
            ]))
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(0, ScheduledVisit::query()->count());
        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());
    }

    public function test_out_of_caseload_supervisor_cannot_override_assign(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);
        $other = Employee::factory()->supervisor()->create();
        $otherUser = $other->user()->firstOrFail();

        $this->actingAs($otherUser)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Trying to override outside my caseload.',
            ]))
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, ScheduledVisit::query()->count());

        $replacement = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $this->approvedLeave($replacement);
        $visit = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => self::DATE,
            'starts_at' => '17:00:00',
            'ends_at' => '19:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($otherUser)
            ->post(route('scheduled-visits.replace', $visit), [
                'employee_id' => $replacement->id,
                'reason' => 'Call-off',
                'override_reason' => 'Outside caseload',
            ])
            ->assertForbidden();

        $this->assertSame($dsp->id, $visit->fresh()->employee_id);
        $this->assertSame(0, ScheduledVisitAvailabilityOverride::query()->count());
        $this->assertNotNull($admin);
    }

    public function test_dsp_cannot_authorize_an_override(): void
    {
        [, , $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Self-assign over my own leave',
            ]))
            ->assertForbidden();

        $this->assertSame(0, ScheduledVisit::query()->count());
    }

    public function test_dsp_acknowledges_own_override_and_supervisor_ops_surfaces_unacknowledged_near_term(): void
    {
        [$admin, $supervisor, $dsp, $client] = $this->scenario();
        $this->approvedLeave($dsp);
        $this->approvedLeave($dsp, '2026-12-15');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'override_reason' => 'Coverage emergency',
            ]))
            ->assertSessionHasNoErrors();

        // Far-future override must not be treated as near-term.
        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), $this->storePayload($client, $dsp, [
                'service_date' => '2026-12-15',
                'override_reason' => 'Far future coverage',
            ]))
            ->assertSessionHasNoErrors();

        $visit = ScheduledVisit::query()->whereDate('service_date', self::DATE)->firstOrFail();
        $supervisorUser = $supervisor->user()->firstOrFail();

        $this->actingAs($supervisorUser)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->has('operations.availability_overrides.unacknowledged', 1)
                ->where('operations.availability_overrides.unacknowledged.0.scheduled_visit_id', $visit->id)
                ->where('operations.availability_overrides.unacknowledged.0.override_reason', 'Coverage emergency')
                ->where('operations.metrics', fn ($metrics) => collect($metrics)
                    ->contains(fn (array $metric): bool => $metric['key'] === 'availability_overrides_unacknowledged' && $metric['value'] === 1))
            );

        // Out-of-caseload supervisor sees nothing.
        $other = Employee::factory()->supervisor()->create();
        $this->actingAs($other->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.availability_overrides.unacknowledged', 0));

        // DSP page exposes the acknowledge capability.
        $dspUser = $dsp->user()->firstOrFail();
        $this->actingAs($dspUser)
            ->get(route('scheduled-visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.acknowledge_availability_override', true)
                ->where('visit.availability_overrides.0.acknowledged', false));

        // Other DSPs and supervisors cannot acknowledge.
        $otherDsp = Employee::factory()->dsp()->create();
        $this->actingAs($otherDsp->user()->firstOrFail())
            ->post(route('scheduled-visits.availability-override.acknowledge', $visit))
            ->assertForbidden();
        $this->actingAs($supervisorUser)
            ->post(route('scheduled-visits.availability-override.acknowledge', $visit))
            ->assertForbidden();

        $this->actingAs($dspUser)
            ->post(route('scheduled-visits.availability-override.acknowledge', $visit))
            ->assertRedirect(route('scheduled-visits.show', $visit));

        $override = ScheduledVisitAvailabilityOverride::query()->where('scheduled_visit_id', $visit->id)->firstOrFail();
        $this->assertNotNull($override->acknowledged_at);
        $this->assertSame($dspUser->id, $override->acknowledged_by_user_id);

        $this->actingAs($supervisorUser)
            ->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.availability_overrides.unacknowledged', 0));
    }

    public function test_ops_board_surfaces_staffing_needed_grace_and_at_risk_queues(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01']);

        $grace = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-19',
            'starts_at' => '11:00:00',
            'ends_at' => '13:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        $atRisk = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-22',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::Cpr)
            ->nonOverrideable()
            ->create();
        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::Cpr,
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-01',
        ]);
        app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        // Staffing Needed has no employee; today's one exercises the null-safe serializer.
        $staffingToday = ScheduledVisit::factory()->forClient($client)->create([
            'employee_id' => null,
            'staffing_needed' => true,
            'service_date' => '2026-10-19',
            'starts_at' => '15:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        $staffingLater = ScheduledVisit::factory()->forClient($client)->create([
            'employee_id' => null,
            'staffing_needed' => true,
            'service_date' => '2026-10-25',
            'starts_at' => '15:00:00',
            'ends_at' => '17:00:00',
            'supervisor_id' => $supervisor->id,
        ]);
        // Excluded: past Staffing Needed and cancelled Staffing Needed.
        ScheduledVisit::factory()->forClient($client)->create([
            'employee_id' => null,
            'staffing_needed' => true,
            'service_date' => '2026-10-10',
            'supervisor_id' => $supervisor->id,
        ]);
        ScheduledVisit::factory()->forClient($client)->cancelled()->create([
            'employee_id' => null,
            'staffing_needed' => true,
            'service_date' => '2026-10-26',
            'supervisor_id' => $supervisor->id,
        ]);

        $grace->refresh();
        $atRisk->refresh();
        $this->assertTrue($grace->isComplianceGrace());
        $this->assertTrue($atRisk->isComplianceAtRisk());

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('operations.metrics', function ($metrics): bool {
                    $values = collect($metrics)->pluck('value', 'key');

                    return $values->get('staffing_needed') === 2
                        && $values->get('compliance_grace') === 1
                        && $values->get('compliance_at_risk') === 1;
                })
                ->has('operations.staffing_needed_visits', 2)
                ->where('operations.staffing_needed_visits.0.scheduled_visit_id', $staffingToday->id)
                ->where('operations.staffing_needed_visits.0.employee', null)
                ->where('operations.staffing_needed_visits.0.staffing_needed', true)
                ->where('operations.staffing_needed_visits.1.scheduled_visit_id', $staffingLater->id)
                ->has('operations.grace_visits', 1)
                ->where('operations.grace_visits.0.scheduled_visit_id', $grace->id)
                ->where('operations.grace_visits.0.compliance_staffing_status', 'grace')
                ->has('operations.at_risk_visits', 1)
                ->where('operations.at_risk_visits.0.scheduled_visit_id', $atRisk->id)
                ->where('operations.at_risk_visits.0.compliance_staffing_status', 'at_risk')
                ->has('operations.today_visits')
            );

        // Caseload scoping is preserved: an unrelated supervisor sees none of these.
        $other = Employee::factory()->supervisor()->create();
        $this->actingAs($other->user()->firstOrFail())
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('operations.metrics', function ($metrics): bool {
                    $values = collect($metrics)->pluck('value', 'key');

                    return $values->get('staffing_needed') === 0
                        && $values->get('compliance_grace') === 0
                        && $values->get('compliance_at_risk') === 0;
                })
                ->has('operations.staffing_needed_visits', 0)
                ->has('operations.grace_visits', 0)
                ->has('operations.at_risk_visits', 0)
            );
    }
}

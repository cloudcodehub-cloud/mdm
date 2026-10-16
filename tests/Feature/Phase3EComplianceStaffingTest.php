<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\DateFormat;
use App\Enums\EmploymentStatus;
use App\Enums\GpsFallbackReason;
use App\Enums\InAppNotificationType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceStaffingEpisode;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\InAppNotification;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ComplianceStaffingService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase3EComplianceStaffingTest extends TestCase
{
    use RefreshDatabase;

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
        Carbon::setTestNow('2026-10-16 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function clockInPayload(): array
    {
        return [
            'location_method' => ClockInLocationMethod::GpsUnavailable->value,
            'location_status' => ClockInLocationStatus::Denied->value,
            'gps_fallback_reason' => GpsFallbackReason::PermissionDenied->value,
        ];
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Client, 3: ScheduledVisit, 4: ScheduledVisit}
     */
    private function assignedVisitsWithHardStop(): array
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->create(['supervisor_id' => $supervisor->id]);
        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01']);

        $grace = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        $later = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-18',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'supervisor_id' => $supervisor->id,
            'status' => ScheduledVisitStatus::Scheduled,
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

        return [$dsp, $supervisor, $client, $grace, $later];
    }

    public function test_newly_effective_hard_stop_preserves_nearest_as_grace_and_marks_later_at_risk(): void
    {
        [$dsp, $supervisor, , $grace, $later] = $this->assignedVisitsWithHardStop();
        User::factory()->admin()->create();

        $result = app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        $this->assertTrue($result['applied']);
        $this->assertSame($grace->id, $result['grace_visit_id']);
        $this->assertSame(1, $result['at_risk_count']);

        $grace->refresh();
        $later->refresh();

        $this->assertTrue($grace->isComplianceGrace());
        $this->assertTrue($later->isComplianceAtRisk());
        $this->assertTrue($later->needs_attention);
        $this->assertFalse($grace->staffing_needed);
        $this->assertFalse($later->staffing_needed);

        $this->assertDatabaseHas('compliance_staffing_episodes', [
            'employee_id' => $dsp->id,
            'grace_scheduled_visit_id' => $grace->id,
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $supervisor->user()->firstOrFail()->id,
            'type' => InAppNotificationType::Compliance->value,
        ]);
        $this->assertTrue(
            InAppNotification::query()
                ->where('type', InAppNotificationType::Compliance->value)
                ->where('title', 'like', '%Hard Stop%')
                ->exists()
        );
    }

    public function test_grace_visit_may_clock_in_but_at_risk_and_new_assignments_remain_blocked(): void
    {
        [$dsp, $supervisor, $client, $grace, $later] = $this->assignedVisitsWithHardStop();
        $admin = User::factory()->admin()->create();

        app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $grace), $this->clockInPayload())
            ->assertRedirect();

        $grace->refresh();
        $this->assertSame(ScheduledVisitStatus::InProgress, $grace->status);

        $episode = ComplianceStaffingEpisode::query()->where('employee_id', $dsp->id)->open()->first();
        $this->assertNotNull($episode?->grace_consumed_at);

        $later->refresh();
        $this->assertTrue($later->isComplianceAtRisk());

        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $later), $this->clockInPayload())
            ->assertSessionHasErrors('scheduled_visit');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-10-20',
                'service_type' => 'Personal Care',
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_restoring_compliance_clears_grace_and_at_risk_marks(): void
    {
        [$dsp, , , $grace, $later] = $this->assignedVisitsWithHardStop();
        app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        EmployeeCredential::query()
            ->where('employee_id', $dsp->id)
            ->where('type', CredentialType::Cpr)
            ->update(['expires_on' => '2027-01-01', 'status' => CredentialStatus::Active]);

        $result = app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        $this->assertTrue($result['cleared']);
        $grace->refresh();
        $later->refresh();
        $this->assertNull($grace->compliance_staffing_status);
        $this->assertNull($later->compliance_staffing_status);
        $this->assertDatabaseMissing('compliance_staffing_episodes', [
            'employee_id' => $dsp->id,
            'resolved_at' => null,
        ]);
    }

    public function test_lifecycle_suspended_skips_compliance_grace_treatment(): void
    {
        [$dsp, , , $grace, $later] = $this->assignedVisitsWithHardStop();

        $dsp->forceFill([
            'employment_status' => EmploymentStatus::Suspended,
            'status_reason' => 'Investigation',
            'status_changed_at' => now(),
        ])->save();

        $result = app(ComplianceStaffingService::class)->syncForEmployee($dsp);

        $this->assertTrue($result['skipped_lifecycle']);
        $this->assertFalse($result['applied']);
        $grace->refresh();
        $later->refresh();
        $this->assertNull($grace->compliance_staffing_status);
        $this->assertNull($later->compliance_staffing_status);
    }

    public function test_unmarked_hard_stop_visit_still_blocks_clock_in_without_grace(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $client = Client::factory()->create(['supervisor_id' => $supervisor->id]);
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

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'supervisor_id' => $supervisor->id,
        ]);

        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->clockInPayload())
            ->assertSessionHasErrors('scheduled_visit');

        $this->assertNull($scheduled->fresh()->compliance_staffing_status);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\ComplianceDateStatus;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\DateFormat;
use App\Enums\GpsFallbackReason;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TimeFormat;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceTemporaryException;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Services\ComplianceEligibilityService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3AComplianceHardStopTest extends TestCase
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
        Carbon::setTestNow('2026-10-16 12:00:00');
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

    public function test_expired_credential_without_hard_stop_requirement_is_warning_only(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::FirstAid,
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-01',
        ]);
        ComplianceRequirement::factory()->forCredentialType(CredentialType::FirstAid)->create([
            'enforcement_level' => ComplianceEnforcementLevel::Warning,
            'override_policy' => ComplianceOverridePolicy::NonOverrideable,
        ]);

        $evaluation = app(ComplianceEligibilityService::class)->evaluate($dsp);

        $this->assertTrue($evaluation['eligible_for_service']);
        $this->assertFalse($evaluation['hard_stop']);
        $this->assertSame('yellow', $evaluation['tone']);
        $this->assertCount(1, $evaluation['warnings']);
        $this->assertSame(ComplianceDateStatus::Expired->value, $evaluation['warnings'][0]['condition']);
    }

    public function test_hard_stop_blocks_clock_in_and_scheduling(): void
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

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'supervisor_id' => $supervisor->id,
                'service_date' => '2026-10-17',
                'service_type' => 'Personal Care',
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasErrors('employee_id');

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
    }

    public function test_supervisor_cannot_override_hard_stop_and_admin_cannot_bypass_non_overrideable(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $requirement = ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::BackgroundCheck)
            ->nonOverrideable()
            ->create();

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::BackgroundCheck,
            'status' => CredentialStatus::Expired,
            'expires_on' => '2026-01-01',
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('employees.compliance-exceptions.store', $dsp), [
                'compliance_requirement_id' => $requirement->id,
                'reason' => 'Supervisor attempting override',
                'starts_at' => '2026-10-16T08:00',
                'ends_at' => '2026-10-20T08:00',
            ])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('employees.compliance-exceptions.store', $dsp), [
                'compliance_requirement_id' => $requirement->id,
                'reason' => 'Admin attempting non-overrideable bypass',
                'starts_at' => '2026-10-16T08:00',
                'ends_at' => '2026-10-20T08:00',
            ])
            ->assertSessionHasErrors('compliance_requirement_id');

        $this->assertDatabaseCount('compliance_temporary_exceptions', 0);
    }

    public function test_admin_temporary_exception_restores_eligibility_without_changing_expired_state(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $requirement = ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::TbScreening)
            ->temporaryExceptionEligible()
            ->create();

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::TbScreening,
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-01',
        ]);

        $eligibility = app(ComplianceEligibilityService::class);
        $this->assertFalse($eligibility->canPerformService($dsp));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('employees.compliance-exceptions.store', $dsp), [
                'compliance_requirement_id' => $requirement->id,
                'reason' => 'Authorized temporary staffing exception',
                'starts_at' => '2026-10-16T00:00',
                'ends_at' => '2026-10-23T00:00',
            ])
            ->assertRedirect();

        $evaluation = $eligibility->evaluate($dsp->fresh());
        $this->assertTrue($evaluation['eligible_for_service']);
        $this->assertFalse($evaluation['hard_stop']);
        $this->assertTrue($evaluation['requirements'][0]['exception_active']);
        $this->assertSame(ComplianceDateStatus::Expired->value, $evaluation['requirements'][0]['condition']);
        $this->assertTrue($evaluation['requirements'][0]['underlying_state_unchanged']);

        $exception = ComplianceTemporaryException::query()->firstOrFail();
        $this->assertSame($admin->id, $exception->created_by_user_id);
        $this->assertSame('Authorized temporary staffing exception', $exception->reason);
        $this->assertNotNull($exception->starts_at);
        $this->assertNotNull($exception->ends_at);
    }

    public function test_dsp_can_view_own_compliance_and_cannot_modify_or_override(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $requirement = ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::Cpr)
            ->temporaryExceptionEligible()
            ->create();

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::Cpr,
            'name' => 'CPR',
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-10-20',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('my-compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('compliance/mine')
                ->where('read_only', true)
                ->where('employee.id', $dsp->id)
                ->where('eligible_for_service', true)
                ->has('credentials', 1)
                ->where('credentials.0.status', ComplianceDateStatus::ExpiringSoon->value)
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('compliance.index'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('employees.compliance-exceptions.store', $dsp), [
                'compliance_requirement_id' => $requirement->id,
                'reason' => 'DSP attempting self override',
                'starts_at' => '2026-10-16T08:00',
                'ends_at' => '2026-10-20T08:00',
            ])
            ->assertForbidden();

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertForbidden();
    }

    public function test_expiring_soon_hard_stop_requirement_does_not_block_service(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::Cpr)
            ->hardStop()
            ->create([
                'override_policy' => ComplianceOverridePolicy::TemporaryException,
            ]);

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::Cpr,
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-10-25',
        ]);

        $evaluation = app(ComplianceEligibilityService::class)->evaluate($dsp);

        $this->assertTrue($evaluation['eligible_for_service']);
        $this->assertFalse($evaluation['hard_stop']);
        $this->assertSame('yellow', $evaluation['tone']);
        $this->assertSame(ComplianceDateStatus::ExpiringSoon->value, $evaluation['warnings'][0]['condition']);
    }

    public function test_missing_hard_stop_requirement_blocks_and_historical_visit_unchanged(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-10-01']);
        ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::PhysicianStatement)
            ->temporaryExceptionEligible()
            ->create();

        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-10-16',
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);

        Carbon::setTestNow('2026-10-16 09:00:00');
        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), $this->clockInPayload())
            ->assertSessionHasErrors('scheduled_visit');

        $this->assertDatabaseMissing('visits', ['scheduled_visit_id' => $scheduled->id]);
        $this->assertSame(ScheduledVisitStatus::Scheduled, $scheduled->fresh()->status);
    }
}

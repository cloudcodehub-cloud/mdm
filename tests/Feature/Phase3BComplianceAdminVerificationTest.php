<?php

namespace Tests\Feature;

use App\Enums\ComplianceDateStatus;
use App\Enums\ComplianceEnforcementLevel;
use App\Enums\ComplianceOverridePolicy;
use App\Enums\ComplianceVerificationStatus;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\DateFormat;
use App\Enums\InAppNotificationType;
use App\Enums\TimeFormat;
use App\Models\ComplianceRequirement;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\InAppNotification;
use App\Models\User;
use App\Services\ComplianceEligibilityService;
use App\Services\ComplianceExpirationReminderService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3BComplianceAdminVerificationTest extends TestCase
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

    public function test_admin_can_create_inactive_requirement_without_blocking_existing_dsps(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('compliance.requirements.store'), [
                'code' => 'cred_cpr',
                'name' => 'CPR',
                'source_kind' => 'credential',
                'source_key' => CredentialType::Cpr->value,
                'applicability' => 'dsp',
                'enforcement_level' => ComplianceEnforcementLevel::HardStop->value,
                'override_policy' => ComplianceOverridePolicy::TemporaryException->value,
                'is_active' => false,
            ])
            ->assertRedirect();

        $requirement = ComplianceRequirement::query()->where('code', 'cred_cpr')->firstOrFail();
        $this->assertFalse($requirement->is_active);
        $this->assertTrue(app(ComplianceEligibilityService::class)->canPerformService($dsp));

        $this->actingAs($admin)
            ->post(route('compliance.requirements.activate', $requirement))
            ->assertRedirect();

        $this->assertFalse(app(ComplianceEligibilityService::class)->canPerformService($dsp->fresh()));
    }

    public function test_supervisor_cannot_manage_requirements_or_verify_evidence(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $credential = EmployeeCredential::factory()->forEmployee($dsp)->pending()->create([
            'verification_status' => ComplianceVerificationStatus::PendingReview,
        ]);

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('compliance.requirements.index'))
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->post(route('compliance-credentials.review', $credential), [
                'decision' => 'verify',
            ])
            ->assertForbidden();
    }

    public function test_pending_review_does_not_restore_eligibility_until_verified(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        ComplianceRequirement::factory()
            ->forCredentialType(CredentialType::Cpr)
            ->temporaryExceptionEligible()
            ->create(['is_active' => true]);

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::Cpr,
            'status' => CredentialStatus::Expired,
            'expires_on' => '2026-01-01',
            'verification_status' => ComplianceVerificationStatus::Verified,
        ]);

        $eligibility = app(ComplianceEligibilityService::class);
        $this->assertFalse($eligibility->canPerformService($dsp));

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('employees.compliance-evidence.store', $dsp), [
                'kind' => 'credential',
                'type' => CredentialType::Cpr->value,
                'name' => 'CPR Renewal',
                'expires_on' => '2027-10-16',
            ])
            ->assertRedirect();

        $pending = EmployeeCredential::query()
            ->where('employee_id', $dsp->id)
            ->where('verification_status', ComplianceVerificationStatus::PendingReview)
            ->firstOrFail();

        $this->assertSame(CredentialStatus::Pending, $pending->status);
        $this->assertFalse($eligibility->canPerformService($dsp->fresh()));
        $this->assertSame(
            ComplianceDateStatus::Expired->value,
            $eligibility->evaluate($dsp->fresh())['requirements'][0]['condition'],
        );

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('compliance-credentials.review', $pending), [
                'decision' => 'verify',
                'note' => 'Documents reviewed',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(ComplianceVerificationStatus::Verified, $pending->verification_status);
        $this->assertSame(CredentialStatus::Active, $pending->status);
        $this->assertSame($admin->id, $pending->verified_by_user_id);
        $this->assertTrue($eligibility->canPerformService($dsp->fresh()));
    }

    public function test_reject_preserves_prior_verified_evidence(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $prior = EmployeeCredential::factory()->forEmployee($dsp)->create([
            'type' => CredentialType::TbScreening,
            'name' => 'TB 2025',
            'status' => CredentialStatus::Active,
            'expires_on' => '2027-01-01',
            'verification_status' => ComplianceVerificationStatus::Verified,
        ]);
        $pending = EmployeeCredential::factory()->forEmployee($dsp)->pending()->create([
            'type' => CredentialType::TbScreening,
            'name' => 'TB replacement',
            'verification_status' => ComplianceVerificationStatus::PendingReview,
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('compliance-credentials.review', $pending), [
                'decision' => 'reject',
                'note' => 'Illegible scan',
            ])
            ->assertRedirect();

        $pending->refresh();
        $prior->refresh();
        $this->assertSame(ComplianceVerificationStatus::Rejected, $pending->verification_status);
        $this->assertSame(ComplianceVerificationStatus::Verified, $prior->verification_status);
        $this->assertSame(CredentialStatus::Active, $prior->status);
    }

    public function test_expiration_reminders_fire_once_per_milestone_and_audience(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $admin = User::factory()->admin()->create();

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'name' => 'CPR',
            'type' => CredentialType::Cpr,
            'status' => CredentialStatus::Active,
            'verification_status' => ComplianceVerificationStatus::Verified,
            'expires_on' => '2026-11-15',
        ]);

        Carbon::setTestNow('2026-10-16 12:00:00');
        $first = app(ComplianceExpirationReminderService::class)->dispatchDue();
        $this->assertSame(1, $first['credentials']);

        $notifications = InAppNotification::query()
            ->where('type', InAppNotificationType::Compliance)
            ->get();
        $this->assertGreaterThanOrEqual(3, $notifications->count());
        $this->assertTrue($notifications->contains(fn (InAppNotification $n) => $n->user_id === $dsp->user_id));
        $this->assertTrue($notifications->contains(fn (InAppNotification $n) => $n->user_id === $supervisor->user_id));
        $this->assertTrue($notifications->contains(fn (InAppNotification $n) => $n->user_id === $admin->id));

        $second = app(ComplianceExpirationReminderService::class)->dispatchDue();
        $this->assertSame(0, $second['credentials']);
        $this->assertSame($notifications->count(), InAppNotification::query()->where('type', InAppNotificationType::Compliance)->count());
    }

    public function test_admin_requirements_page_and_dsp_cannot_access_it(): void
    {
        $admin = User::factory()->admin()->create();
        $dsp = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->get(route('compliance.requirements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('compliance/requirements'));

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('compliance.requirements.index'))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ComplianceDateStatus;
use App\Enums\CredentialStatus;
use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\User;
use App\Services\ComplianceStatusService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-09-11 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_sees_all_and_supervisor_sees_assigned_dsps_only(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($other)->create();

        EmployeeCredential::factory()->forEmployee($dsp)->create([
            'name' => 'CPR',
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-20',
        ]);
        EmployeeCredential::factory()->forEmployee($otherDsp)->create([
            'name' => 'First Aid',
            'status' => CredentialStatus::Active,
            'expires_on' => '2027-01-01',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('compliance/index')
                ->where('summary.expiring_soon', 1)
                ->where('summary.valid', 1)
                ->where('summary.missing', 0)
                ->has('items', 2)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.employee_id', $dsp->id)
            );

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.employee_id', $otherDsp->id)
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('compliance.index'))
            ->assertForbidden();
    }

    public function test_expiry_is_derived_from_dates_and_threshold(): void
    {
        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'MDM - Magic Data Management',
            'timezone' => 'America/New_York',
            'date_format' => DateFormat::MonthDayYear->value,
            'time_format' => TimeFormat::TwelveHour->value,
            'first_day_of_week' => 0,
            'credential_expiring_soon_days' => 10,
        ]);

        $dsp = Employee::factory()->dsp()->create();
        $staleActive = EmployeeCredential::factory()->forEmployee($dsp)->create([
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-01',
        ]);
        $soon = EmployeeCredential::factory()->forEmployee($dsp)->create([
            'status' => CredentialStatus::Active,
            'expires_on' => '2026-09-18',
        ]);
        $valid = EmployeeCredential::factory()->forEmployee($dsp)->create([
            'status' => CredentialStatus::Active,
            'expires_on' => '2027-01-01',
        ]);
        $training = EmployeeTraining::factory()->forEmployee($dsp)->create([
            'status' => TrainingStatus::Completed,
            'expires_on' => '2026-09-15',
        ]);

        $classifier = app(ComplianceStatusService::class);
        $this->assertSame(ComplianceDateStatus::Expired, $classifier->forCredential($staleActive));
        $this->assertSame(ComplianceDateStatus::ExpiringSoon, $classifier->forCredential($soon));
        $this->assertSame(ComplianceDateStatus::Valid, $classifier->forCredential($valid));
        $this->assertSame(ComplianceDateStatus::ExpiringSoon, $classifier->forTraining($training));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('expiring_soon_days', 10)
                ->where('summary.expired', 1)
                ->where('summary.expiring_soon', 2)
                ->where('summary.valid', 1)
                ->where('summary.missing', 0)
                ->has('employees', 1)
            );
    }
}

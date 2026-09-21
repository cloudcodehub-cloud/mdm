<?php

namespace Tests\Feature\Domain;

use App\Enums\AssignmentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Models\AttendanceCorrection;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\DemoSeedRecord;
use App\Models\DspWeeklyAvailability;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Services\SettingsService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-11 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_primary_demo_accounts_and_operational_chain_exist(): void
    {
        $this->seed(DemoDataSeeder::class);

        $admin = User::query()->where('email', DemoSeeder::ADMIN_EMAIL)->firstOrFail();
        $supervisor = User::query()->where('email', DemoSeeder::SUPERVISOR_EMAIL)->firstOrFail();
        $dsp = User::query()->where('email', DemoSeeder::DSP_EMAIL)->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertNull($admin->employee);
        $this->assertTrue($supervisor->isSupervisor());
        $this->assertTrue($dsp->isDsp());

        $this->assertNotNull($supervisor->employee);
        $this->assertSame(EmploymentStatus::Active, $supervisor->employee->employment_status);
        $this->assertNotNull($dsp->employee);
        $this->assertSame(EmploymentStatus::Active, $dsp->employee->employment_status);

        $this->assertTrue(auth()->attempt([
            'email' => DemoSeeder::ADMIN_EMAIL,
            'password' => DemoSeeder::PASSWORD,
        ]));
        auth()->logout();

        $this->assertTrue(
            DspWeeklyAvailability::query()
                ->where('employee_id', $dsp->employee->id)
                ->where('is_available', true)
                ->exists(),
        );

        $this->assertTrue(Client::query()->where('client_number', 'CLT-3001')->exists());
        $this->assertSame(DemoDataSeeder::AGENCY_NAME, OrganizationSetting::query()->value('organization_name'));
        $this->assertSame(DemoDataSeeder::TIMEZONE, OrganizationSetting::query()->value('timezone'));
        $this->assertSame(DemoDataSeeder::AGENCY_CITY, OrganizationSetting::query()->value('city'));
        $this->assertSame(DemoDataSeeder::AGENCY_STATE, OrganizationSetting::query()->value('state'));
        $this->assertSame(DemoDataSeeder::AGENCY_POSTAL, OrganizationSetting::query()->value('postal_code'));
        $this->assertSame('Reynoldsburg, OH', app(SettingsService::class)->agencyLocation()['label']);
        $this->assertTrue(
            ClientDspAssignment::query()
                ->where('status', AssignmentStatus::Active)
                ->where('employee_id', $dsp->employee->id)
                ->exists(),
        );

        $this->assertTrue(CarePlan::query()->currentlyActive()->exists());
        $this->assertTrue(CarePlanTaskTemplate::query()->active()->exists());
        $this->assertTrue(ScheduledVisit::query()->exists());
        $this->assertTrue(Visit::query()->where('status', VisitStatus::Completed)->whereNotNull('clocked_out_at')->exists());

        $overrideVisit = ScheduledVisit::query()->findOrFail(
            DemoSeedRecord::query()->where('key', 'maya-elena-yesterday')->value('seedable_id'),
        );
        $this->assertTrue(ScheduledVisitTaskOverride::query()->where('scheduled_visit_id', $overrideVisit->id)->where('included', false)->exists());
        $this->assertTrue(ScheduledVisitOneOffTask::query()->where('scheduled_visit_id', $overrideVisit->id)->exists());
        $this->assertSame(
            4,
            $overrideVisit->client->carePlans()->currentlyActive()->firstOrFail()->taskTemplates()->active()->count(),
        );
        $this->assertStringNotContainsString('[[mdm-demo:', (string) $overrideVisit->notes);

        $this->assertTrue(AttendanceCorrection::query()->exists());
        $this->assertTrue(
            ScheduledVisit::query()
                ->whereDate('service_date', '2026-09-11')
                ->where('status', ScheduledVisitStatus::Scheduled)
                ->where('employee_id', $dsp->employee->id)
                ->exists(),
        );
    }

    public function test_demo_seeder_is_idempotent_for_key_identities(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, User::query()->where('email', DemoSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', DemoSeeder::SUPERVISOR_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', DemoSeeder::DSP_EMAIL)->count());
        $this->assertSame(1, Employee::query()->where('employee_number', 'EMP-2001')->count());
        $this->assertSame(1, Client::query()->where('client_number', 'CLT-3001')->count());
        $this->assertSame(1, User::query()->where('role', Role::Admin)->count());
        $this->assertSame(2, User::query()->where('role', Role::Supervisor)->count());
        $this->assertSame(8, User::query()->where('role', Role::Dsp)->count());
        $this->assertSame(
            1,
            DemoSeedRecord::query()->where('key', 'maya-elena-today')->count(),
        );
        $todayVisit = ScheduledVisit::query()->find(
            DemoSeedRecord::query()->where('key', 'maya-elena-today')->value('seedable_id'),
        );
        $this->assertNotNull($todayVisit);
        $this->assertStringNotContainsString('[[mdm-demo:', (string) $todayVisit->notes);
        $this->assertSame(DemoDataSeeder::AGENCY_CITY, OrganizationSetting::query()->value('city'));
        $this->assertSame(DemoDataSeeder::AGENCY_STATE, OrganizationSetting::query()->value('state'));
        $this->assertSame(DemoDataSeeder::AGENCY_POSTAL, OrganizationSetting::query()->value('postal_code'));
        $this->assertSame(
            DemoDataSeeder::AGENCY_NAME,
            OrganizationSetting::query()->value('organization_name'),
        );
        $this->assertSame(
            DemoDataSeeder::TIMEZONE,
            OrganizationSetting::query()->value('timezone'),
        );
    }

    public function test_demo_seeder_sets_agency_identity_without_overwriting_custom_agencies(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(DemoDataSeeder::AGENCY_NAME, app(SettingsService::class)->agencyName());
        $this->assertSame(DemoDataSeeder::TIMEZONE, app(SettingsService::class)->timezone());
        $this->assertSame('Reynoldsburg, OH', app(SettingsService::class)->agencyLocation()['label']);

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(DemoDataSeeder::AGENCY_NAME, OrganizationSetting::query()->value('organization_name'));
        $this->assertSame(1, OrganizationSetting::query()->count());

        app(SettingsService::class)->updateOrganization([
            'organization_name' => 'Custom Production Agency',
            'timezone' => 'America/Chicago',
        ]);

        $this->seed(DemoDataSeeder::class);

        $settings = OrganizationSetting::query()->firstOrFail();
        $this->assertSame('Custom Production Agency', $settings->organization_name);
        $this->assertSame('America/Chicago', $settings->timezone);
    }

    public function test_demo_visible_notes_and_reasons_do_not_include_internal_markers(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(0, \App\Models\DspAvailabilityRequest::query()->where('reason', 'like', '%[[mdm-demo:%')->count());
        $this->assertSame(0, ScheduledVisit::query()->where('notes', 'like', '%[[mdm-demo:%')->count());
        $this->assertSame(0, \App\Models\EmployeeTimeOff::query()->where('reason', 'like', '%[[mdm-demo:%')->count());
        $this->assertSame(0, \App\Models\AttendanceCorrection::query()->where('reason', 'like', '%[[mdm-demo:%')->count());
        $this->assertGreaterThan(0, VisitException::query()->open()->count());

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(1, DemoSeedRecord::query()->where('key', 'exception-maya-critical-skip')->count());
        $this->assertSame(1, DemoSeedRecord::query()->where('key', 'exception-dana-client-refusal')->count());
        $this->assertSame(1, DemoSeedRecord::query()->where('key', 'exception-nina-unfinished')->count());
        $this->assertSame(1, DemoSeedRecord::query()->where('key', 'exception-tess-gps')->count());
    }
}

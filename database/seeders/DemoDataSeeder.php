<?php

namespace Database\Seeders;

use App\Enums\AnnouncementAudience;
use App\Enums\AssignmentStatus;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\AvailabilityRequestType;
use App\Enums\CarePlanStatus;
use App\Enums\ClientStatus;
use App\Enums\ClockInLocationMethod;
use App\Enums\ClockInLocationStatus;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\PreferredDaypart;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskPreferredTiming;
use App\Enums\TaskRecurrence;
use App\Enums\TrainingStatus;
use App\Enums\VisitRecurrencePattern;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\DspAvailabilityException;
use App\Models\DspAvailabilityRequest;
use App\Models\DspWeeklyAvailability;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTimeOff;
use App\Models\EmployeeTraining;
use App\Models\OrganizationSetting;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\ScheduledVisitSeries;
use App\Models\ScheduledVisitTaskOverride;
use App\Models\ShiftTemplate;
use App\Models\SkipReason;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\SettingsService;
use App\Services\VisitTaskGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class DemoDataSeeder extends Seeder
{
    /**
     * Marker prefix used to upsert demo operational rows without touching unrelated local data.
     */
    public const MARKER_PREFIX = '[[mdm-demo:';

    /**
     * Seed a coherent local/demo agency. Skipped in production.
     *
     * Dates are relative to the organization calendar date at run time so dashboards
     * stay current. Re-runs update the same demo keys instead of duplicating identities.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder is not run in production.');

            return;
        }

        $this->call(TaskCatalogSeeder::class);
        $this->call(CareServiceSeeder::class);

        $this->seedOrganization();
        $this->seedShiftTemplates();
        $this->seedSkipReasons();

        $today = Carbon::parse(app(SettingsService::class)->today())->startOfDay();

        $people = $this->seedPeople();
        $clients = $this->seedClients($people);
        $this->seedAssignments($people, $clients);
        $this->seedCredentials($people, $today);
        $this->seedTrainings($people, $today);
        $this->seedAuthorizations($clients, $today);
        $this->seedClientServices($clients);
        $plans = $this->seedCarePlans($clients, $today);
        $this->seedAvailability($people, $today);
        $this->seedTimeOff($people, $today);
        $this->seedScheduleAndVisits($people, $clients, $plans, $today);
        $this->seedAnnouncements($people, $today);
        $this->seedMessages($people, $today);

        $admin = User::query()->where('email', DemoSeeder::ADMIN_EMAIL)->firstOrFail();

        if ($admin->employee()->exists()) {
            throw new \RuntimeException('Demo admin accounts must not have an employee profile.');
        }
    }

    private function seedOrganization(): void
    {
        $settings = app(SettingsService::class)->current();
        $current = $settings->organization_name;
        $appName = (string) config('app.name', 'MDM - Magic Data Management');

        if (in_array($current, [$appName, 'MDM - Magic Data Management', 'Laravel'], true)) {
            OrganizationSetting::query()->whereKey($settings->id)->update([
                'organization_name' => 'Lakeside Supported Living',
            ]);
        }
    }

    /**
     * @return array<string, Employee|User>
     */
    private function seedPeople(): array
    {
        $admin = $this->upsertUser('Avery Quinn', DemoSeeder::ADMIN_EMAIL, Role::Admin);

        $jordanUser = $this->upsertUser('Jordan Hale', DemoSeeder::SUPERVISOR_EMAIL, Role::Supervisor);
        $priyaUser = $this->upsertUser('Priya Nair', 'priya.nair@mdm.test', Role::Supervisor);

        $jordan = $this->upsertEmployee('EMP-1001', $jordanUser, [
            'first_name' => 'Jordan',
            'middle_name' => 'Lee',
            'last_name' => 'Hale',
            'email' => DemoSeeder::SUPERVISOR_EMAIL,
            'phone' => '555-201-1001',
            'date_of_birth' => '1986-04-12',
            'address_line_1' => '14 Maple Court',
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44001',
            'emergency_contact_name' => 'Sam Hale',
            'emergency_contact_relationship' => 'Spouse',
            'emergency_contact_phone' => '555-201-1099',
            'hired_on' => '2018-03-01',
            'employment_status' => EmploymentStatus::Active,
            'job_title' => 'Supervisor',
            'job_type' => JobType::Supervisor,
            'notes' => 'North caseload supervisor.',
        ]);

        $priya = $this->upsertEmployee('EMP-1002', $priyaUser, [
            'first_name' => 'Priya',
            'middle_name' => null,
            'last_name' => 'Nair',
            'email' => 'priya.nair@mdm.test',
            'phone' => '555-202-1002',
            'date_of_birth' => '1989-11-03',
            'address_line_1' => '88 Harbor Lane',
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44002',
            'emergency_contact_name' => 'Arjun Nair',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '555-202-1098',
            'hired_on' => '2019-07-15',
            'employment_status' => EmploymentStatus::Active,
            'job_title' => 'Supervisor',
            'job_type' => JobType::Supervisor,
            'notes' => 'South caseload supervisor.',
        ]);

        $mayaUser = $this->upsertUser('Maya Chen', DemoSeeder::DSP_EMAIL, Role::Dsp);
        $luisUser = $this->upsertUser('Luis Ortega', 'luis.ortega@mdm.test', Role::Dsp);
        $ninaUser = $this->upsertUser('Nina Brooks', 'nina.brooks@mdm.test', Role::Dsp);
        $owenUser = $this->upsertUser('Owen Patel', 'owen.patel@mdm.test', Role::Dsp);
        $danaUser = $this->upsertUser('Dana Ruiz', 'dana.ruiz@mdm.test', Role::Dsp);
        $tessUser = $this->upsertUser('Tess Morgan', 'tess.morgan@mdm.test', Role::Dsp);
        $chrisUser = $this->upsertUser('Chris Walsh', 'chris.walsh@mdm.test', Role::Dsp);
        $saraUser = $this->upsertUser('Sara Kim', 'sara.kim@mdm.test', Role::Dsp);

        $maya = $this->upsertDsp($mayaUser, 'EMP-2001', 'Maya', 'Grace', 'Chen', DemoSeeder::DSP_EMAIL, '555-301-2001', '1994-02-18', $jordan->id, '2021-01-11', '21 Birch Street', 'Lakeside', '44004', 'Wei Chen', 'Parent', '555-301-2091');
        $luis = $this->upsertDsp($luisUser, 'EMP-2002', 'Luis', null, 'Ortega', 'luis.ortega@mdm.test', '555-301-2002', '1992-09-27', $jordan->id, '2020-06-08', '9 Riverview Drive', 'Lakeside', '44004', 'Carmen Ortega', 'Spouse', '555-301-2092');
        $nina = $this->upsertDsp($ninaUser, 'EMP-2003', 'Nina', 'Rae', 'Brooks', 'nina.brooks@mdm.test', '555-301-2003', '1996-05-04', $priya->id, '2022-04-19', '40 Orchard Road', 'Lakeside', '44006', 'Helen Brooks', 'Parent', '555-301-2093');
        $owen = $this->upsertDsp($owenUser, 'EMP-2004', 'Owen', null, 'Patel', 'owen.patel@mdm.test', '555-301-2004', '1991-12-22', $priya->id, '2019-10-02', '12 Lakeview Terrace', 'Lakeside', '44006', 'Anita Patel', 'Sibling', '555-301-2094');
        $dana = $this->upsertDsp($danaUser, 'EMP-2006', 'Dana', null, 'Ruiz', 'dana.ruiz@mdm.test', '555-301-2006', '1995-07-09', $jordan->id, '2023-03-14', '5 Willow Court', 'Lakeside', '44004', 'Marco Ruiz', 'Spouse', '555-301-2096');
        $tess = $this->upsertDsp($tessUser, 'EMP-2007', 'Tess', 'Anne', 'Morgan', 'tess.morgan@mdm.test', '555-301-2007', '1990-01-30', $priya->id, '2024-02-05', '18 Harbor Walk', 'Lakeside', '44006', 'Riley Morgan', 'Partner', '555-301-2097');

        $chris = $this->upsertEmployee('EMP-2008', $chrisUser, [
            'first_name' => 'Chris',
            'middle_name' => null,
            'last_name' => 'Walsh',
            'email' => 'chris.walsh@mdm.test',
            'phone' => '555-301-2008',
            'date_of_birth' => '1988-06-21',
            'address_line_1' => '33 Pine Crescent',
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44005',
            'emergency_contact_name' => 'Pat Walsh',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '555-301-2098',
            'hired_on' => '2019-08-12',
            'employment_status' => EmploymentStatus::Inactive,
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
            'supervisor_id' => $jordan->id,
            'notes' => 'Inactive DSP retained for lifecycle screens. Not assigned active work.',
        ]);

        $sara = $this->upsertEmployee('EMP-2005', $saraUser, [
            'first_name' => 'Sara',
            'middle_name' => null,
            'last_name' => 'Kim',
            'email' => 'sara.kim@mdm.test',
            'phone' => '555-301-2005',
            'date_of_birth' => '1993-08-14',
            'address_line_1' => '7 Cedar Way',
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44005',
            'emergency_contact_name' => 'Daniel Kim',
            'emergency_contact_relationship' => 'Parent',
            'emergency_contact_phone' => '555-301-2095',
            'hired_on' => '2017-05-01',
            'terminated_on' => '2025-12-31',
            'employment_status' => EmploymentStatus::Terminated,
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
            'supervisor_id' => $jordan->id,
            'notes' => 'Historical employee retained after termination.',
        ]);

        return compact(
            'admin',
            'jordanUser',
            'priyaUser',
            'jordan',
            'priya',
            'mayaUser',
            'luisUser',
            'ninaUser',
            'owenUser',
            'danaUser',
            'tessUser',
            'chrisUser',
            'saraUser',
            'maya',
            'luis',
            'nina',
            'owen',
            'dana',
            'tess',
            'chris',
            'sara',
        );
    }

    /**
     * @param  array<string, mixed>  $people
     * @return array<string, Client>
     */
    private function seedClients(array $people): array
    {
        $jordanId = $people['jordan']->id;
        $priyaId = $people['priya']->id;

        return [
            'elena' => $this->upsertClient('CLT-3001', 'Elena', 'Marie', 'Vasquez', $jordanId, ClientStatus::Active, '1998-03-14', '102 Lakeshore Drive', '555-401-3001', 'Rosa Vasquez'),
            'theo' => $this->upsertClient('CLT-3002', 'Theo', null, 'Anders', $jordanId, ClientStatus::Active, '1991-11-02', '44 Garden Path', '555-401-3002', 'Ingrid Anders'),
            'harper' => $this->upsertClient('CLT-3003', 'Harper', 'Jane', 'Cole', $jordanId, ClientStatus::Active, '2001-07-19', '8 Meadow Lane', '555-401-3003', 'Patrick Cole'),
            'noah' => $this->upsertClient('CLT-3007', 'Noah', 'James', 'Grant', $jordanId, ClientStatus::Active, '1987-05-08', '27 Oak Hill', '555-401-3007', 'Ellen Grant'),
            'malik' => $this->upsertClient('CLT-3004', 'Malik', null, 'Hassan', $priyaId, ClientStatus::Active, '1995-09-23', '61 Riverbend Court', '555-401-3004', 'Amina Hassan'),
            'ruby' => $this->upsertClient('CLT-3005', 'Ruby', 'Ann', 'Foster', $priyaId, ClientStatus::Active, '1999-12-01', '3 Sunset Place', '555-401-3005', 'June Foster'),
            'chloe' => $this->upsertClient('CLT-3008', 'Chloe', null, 'Nguyen', $priyaId, ClientStatus::Active, '1993-04-27', '19 Bayberry Street', '555-401-3008', 'Minh Nguyen'),
            'ian' => $this->upsertClient('CLT-3006', 'Ian', null, 'Brooks', $priyaId, ClientStatus::Inactive, '1984-02-11', '70 Closed Lane', '555-401-3006', 'Helen Brooks'),
        ];
    }

    /**
     * @param  array<string, mixed>  $people
     * @param  array<string, Client>  $clients
     */
    private function seedAssignments(array $people, array $clients): void
    {
        $this->upsertAssignment($people['maya'], $clients['elena'], AssignmentStatus::Active, '2024-01-15');
        $this->upsertAssignment($people['maya'], $clients['theo'], AssignmentStatus::Active, '2024-03-01');
        $this->upsertAssignment($people['luis'], $clients['elena'], AssignmentStatus::Active, '2024-02-10');
        $this->upsertAssignment($people['luis'], $clients['harper'], AssignmentStatus::Active, '2023-11-20');
        $this->upsertAssignment($people['dana'], $clients['theo'], AssignmentStatus::Active, '2023-08-01');
        $this->upsertAssignment($people['dana'], $clients['noah'], AssignmentStatus::Active, '2024-09-01');
        $this->upsertAssignment($people['nina'], $clients['malik'], AssignmentStatus::Active, '2024-05-06');
        $this->upsertAssignment($people['nina'], $clients['ruby'], AssignmentStatus::Active, '2024-06-12');
        $this->upsertAssignment($people['tess'], $clients['ruby'], AssignmentStatus::Active, '2024-09-18');
        $this->upsertAssignment($people['tess'], $clients['chloe'], AssignmentStatus::Active, '2025-01-06');
        $this->upsertAssignment($people['owen'], $clients['malik'], AssignmentStatus::Active, '2024-04-02');
        $this->upsertAssignment($people['owen'], $clients['chloe'], AssignmentStatus::Active, '2025-02-10');
        $this->upsertAssignment($people['maya'], $clients['harper'], AssignmentStatus::Inactive, '2023-01-08', '2023-10-31', 'Previous assignment closed when coverage changed.');
    }

    private function seedShiftTemplates(): void
    {
        $templates = [
            ['name' => '7–3', 'code' => 'day_7_3', 'starts_at' => '07:00:00', 'ends_at' => '15:00:00', 'description' => 'Day shift, 7 a.m. to 3 p.m.'],
            ['name' => '3–11', 'code' => 'evening_3_11', 'starts_at' => '15:00:00', 'ends_at' => '23:00:00', 'description' => 'Evening shift, 3 p.m. to 11 p.m.'],
            ['name' => '11–7', 'code' => 'overnight_11_7', 'starts_at' => '23:00:00', 'ends_at' => '07:00:00', 'description' => 'Overnight shift, 11 p.m. to 7 a.m. next calendar day.'],
        ];

        foreach ($templates as $template) {
            ShiftTemplate::query()->updateOrCreate(
                ['code' => $template['code']],
                [...$template, 'is_active' => true],
            );
        }
    }

    private function seedSkipReasons(): void
    {
        $reasons = [
            ['name' => 'Client refused', 'code' => 'client_refused', 'requires_comment' => false, 'sort_order' => 1],
            ['name' => 'Not applicable', 'code' => 'not_applicable', 'requires_comment' => false, 'sort_order' => 2],
            ['name' => 'Already completed', 'code' => 'already_completed', 'requires_comment' => false, 'sort_order' => 3],
            ['name' => 'Safety concern', 'code' => 'safety_concern', 'requires_comment' => false, 'sort_order' => 4],
            ['name' => 'Client unavailable', 'code' => 'client_unavailable', 'requires_comment' => false, 'sort_order' => 5],
            ['name' => 'Equipment or supply unavailable', 'code' => 'equipment_unavailable', 'requires_comment' => false, 'sort_order' => 6],
            ['name' => 'Other', 'code' => 'other', 'requires_comment' => true, 'sort_order' => 7],
        ];

        foreach ($reasons as $reason) {
            SkipReason::query()->updateOrCreate(
                ['code' => $reason['code']],
                [...$reason, 'is_active' => true],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedCredentials(array $people, CarbonInterface $today): void
    {
        $soon = $today->copy()->addDays(12)->toDateString();
        $future = $today->copy()->addYears(2)->toDateString();
        $expired = $today->copy()->subMonths(8)->toDateString();
        $issued = $today->copy()->subYear()->toDateString();

        $this->upsertCredential($people['jordan'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, $issued, $future);
        $this->upsertCredential($people['priya'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, $issued, $future);

        $this->upsertCredential($people['maya'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, $issued, $future, 'American Red Cross', 'CPR-2001');
        $this->upsertCredential($people['maya'], CredentialType::FirstAid, 'First Aid Certification', CredentialStatus::Active, $issued, $future, 'American Red Cross', 'FA-2001');
        $this->upsertCredential($people['maya'], CredentialType::DriversLicense, "Driver's License", CredentialStatus::Active, $issued, $today->copy()->addYears(4)->toDateString(), 'Ohio BMV', 'OH-DL-2001');

        $this->upsertCredential($people['luis'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Expired, $today->copy()->subYears(3)->toDateString(), $expired, 'American Red Cross', 'CPR-2002');
        $this->upsertCredential($people['luis'], CredentialType::FirstAid, 'First Aid Certification', CredentialStatus::Active, $issued, $future, 'American Red Cross', 'FA-2002');

        $this->upsertCredential($people['nina'], CredentialType::BackgroundCheck, 'Background Check', CredentialStatus::Active, $issued, $future, 'County Board', 'BGC-2003');
        $this->upsertCredential($people['nina'], CredentialType::MedicationAdministration, 'Medication Administration', CredentialStatus::Active, $issued, $future, 'Agency Nursing', 'MED-2003');

        $this->upsertCredential($people['owen'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, $issued, $future);
        $this->upsertCredential($people['owen'], CredentialType::TbScreening, 'TB Screening', CredentialStatus::Active, $today->copy()->subMonths(2)->toDateString(), $today->copy()->addMonths(10)->toDateString(), 'County Health');
        $this->upsertCredential($people['owen'], CredentialType::DriversLicense, "Driver's License", CredentialStatus::Pending, null, null, 'Ohio BMV');

        $this->upsertCredential($people['dana'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, $issued, $soon, 'American Red Cross', 'CPR-2006');
        $this->upsertCredential($people['dana'], CredentialType::FirstAid, 'First Aid Certification', CredentialStatus::Active, $issued, $future);

        $this->upsertCredential($people['sara'], CredentialType::Cpr, 'CPR Certification', CredentialStatus::Expired, '2020-05-01', '2022-05-01', 'American Red Cross', 'CPR-2005', 'Historical credential retained after termination.');
        $this->upsertCredential($people['chris'], CredentialType::TbScreening, 'TB Screening', CredentialStatus::Expired, $today->copy()->subYears(2)->toDateString(), $today->copy()->subYear()->toDateString(), 'County Health');
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedTrainings(array $people, CarbonInterface $today): void
    {
        $this->upsertTraining($people['maya'], 'DSP Orientation', 'Agency Training', '2021-01-20', null, '8.00', TrainingStatus::Completed);
        $this->upsertTraining($people['maya'], 'Bloodborne Pathogens', 'Agency Training', $today->copy()->subMonths(6)->toDateString(), $today->copy()->addYear()->toDateString(), '2.00', TrainingStatus::Completed);
        $this->upsertTraining($people['luis'], 'Crisis Intervention', 'County Board', $today->copy()->subMonths(3)->toDateString(), $today->copy()->addYear()->toDateString(), '8.00', TrainingStatus::Completed);
        $this->upsertTraining($people['nina'], 'Medication Administration', 'Agency Nursing', $today->copy()->subMonths(10)->toDateString(), $today->copy()->addMonths(14)->toDateString(), '4.00', TrainingStatus::Completed);
        $this->upsertTraining($people['nina'], 'Fire Safety', 'Agency Training', null, null, '2.00', TrainingStatus::InProgress);
        $this->upsertTraining($people['owen'], 'DSP Orientation', 'Agency Training', '2019-10-15', null, '8.00', TrainingStatus::Completed);
        $this->upsertTraining($people['dana'], 'DSP Orientation', 'Agency Training', '2023-03-20', null, '8.00', TrainingStatus::Completed);
        $this->upsertTraining($people['tess'], 'DSP Orientation', 'Agency Training', '2024-02-12', null, '8.00', TrainingStatus::Completed);
        $this->upsertTraining($people['sara'], 'DSP Orientation', 'Agency Training', '2017-05-10', null, '8.00', TrainingStatus::Completed, 'Historical training retained after termination.');
        $this->upsertTraining($people['chris'], 'DSP Orientation', 'Agency Training', '2019-08-20', null, '8.00', TrainingStatus::Completed, 'Inactive employee historical training.');
    }

    /**
     * @param  array<string, Client>  $clients
     */
    private function seedAuthorizations(array $clients, CarbonInterface $today): void
    {
        $yearStart = $today->copy()->startOfYear()->toDateString();
        $yearEnd = $today->copy()->endOfYear()->toDateString();

        $this->upsertAuthorization($clients['elena'], 'AUTH-3001001', 'Ohio Medicaid', 'Residential Habilitation', $yearStart, $yearEnd, '40.00', AuthorizationUnit::Hour, AuthorizationStatus::Active, 'Weekly authorized hours.');
        $this->upsertAuthorization($clients['theo'], 'AUTH-3002001', 'County Board Waiver', 'Personal Care', $today->copy()->subMonths(6)->toDateString(), $yearEnd, '20.00', AuthorizationUnit::Hour, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['harper'], 'AUTH-3003001', 'Ohio Medicaid', 'Community Integration', $today->copy()->addMonth()->toDateString(), $today->copy()->addMonths(7)->toDateString(), '15.00', AuthorizationUnit::Hour, AuthorizationStatus::Pending, 'Renewal pending payer approval.');
        $this->upsertAuthorization($clients['harper'], 'AUTH-3003000', 'Ohio Medicaid', 'Community Integration', $today->copy()->subYear()->toDateString(), $today->copy()->addDays(10)->toDateString(), '15.00', AuthorizationUnit::Hour, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['noah'], 'AUTH-3007001', 'Ohio Medicaid', 'Residential Habilitation', $yearStart, $yearEnd, '25.00', AuthorizationUnit::Hour, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['malik'], 'AUTH-3004001', 'Ohio Medicaid', 'Residential Habilitation', $today->copy()->subMonths(7)->toDateString(), $today->copy()->addMonths(5)->toDateString(), '40.00', AuthorizationUnit::Hour, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['ruby'], 'AUTH-3005001', 'Private Pay', 'Personal Care', $yearStart, $today->copy()->addMonths(3)->toDateString(), '10.00', AuthorizationUnit::Visit, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['chloe'], 'AUTH-3008001', 'County Board Waiver', 'Community Integration', $yearStart, $yearEnd, '12.00', AuthorizationUnit::Hour, AuthorizationStatus::Active);
        $this->upsertAuthorization($clients['ian'], 'AUTH-3006001', 'County Board Waiver', 'Personal Care', $today->copy()->subYears(2)->toDateString(), $today->copy()->subYear()->toDateString(), '20.00', AuthorizationUnit::Hour, AuthorizationStatus::Expired, 'Closed with inactive client record.');
    }

    /**
     * @param  array<string, Client>  $clients
     */
    private function seedClientServices(array $clients): void
    {
        $bySlug = CareService::query()->pluck('id', 'slug');

        $clients['elena']->careServices()->sync(array_filter([$bySlug['residential-habilitation'] ?? null, $bySlug['community-integration'] ?? null]));
        $clients['theo']->careServices()->sync(array_filter([$bySlug['personal-care'] ?? null]));
        $clients['harper']->careServices()->sync(array_filter([$bySlug['community-integration'] ?? null, $bySlug['appointment-escort'] ?? null]));
        $clients['noah']->careServices()->sync(array_filter([$bySlug['homemaker'] ?? null, $bySlug['independent-living'] ?? null]));
        $clients['malik']->careServices()->sync(array_filter([$bySlug['residential-habilitation'] ?? null, $bySlug['overnight-supervision'] ?? null]));
        $clients['ruby']->careServices()->sync(array_filter([$bySlug['personal-care'] ?? null]));
        $clients['chloe']->careServices()->sync(array_filter([$bySlug['community-integration'] ?? null]));
    }

    /**
     * @param  array<string, Client>  $clients
     * @return array<string, CarePlan>
     */
    private function seedCarePlans(array $clients, CarbonInterface $today): array
    {
        $yearStart = $today->copy()->startOfYear()->toDateString();
        $yearEnd = $today->copy()->endOfYear()->toDateString();

        $elenaPlan = $this->upsertCarePlan($clients['elena'], 'Elena Vasquez ISP 2026', $yearStart, $yearEnd, CarePlanStatus::Active);
        $this->deactivateDuplicateCarePlans($clients['elena'], $elenaPlan);
        $this->upsertTask($elenaPlan, 'Morning personal care', TaskRecurrence::Daily, 1, 'Support hygiene, dressing, and breakfast.', TaskPreferredTiming::Morning, true, false, true, false);
        $this->upsertTask($elenaPlan, 'Meal preparation', TaskRecurrence::Daily, 2, 'Prepare a preferred meal and document intake.', TaskPreferredTiming::Morning, true, true, true, false);
        $this->upsertTask($elenaPlan, 'Community outing', TaskRecurrence::Daily, 3, 'Support a community activity of Elena’s choosing.', TaskPreferredTiming::Afternoon, true, false, true, false);
        $this->upsertTask($elenaPlan, 'Safety check', TaskRecurrence::Daily, 4, 'Complete home safety check before leaving.', TaskPreferredTiming::DuringVisit, true, false, true, true);

        $theoPlan = $this->upsertCarePlan($clients['theo'], 'Theo Anders Personal Care Plan', $today->copy()->subMonths(6)->toDateString(), $yearEnd, CarePlanStatus::Active);
        $this->upsertTask($theoPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->upsertTask($theoPlan, 'Grocery shopping support', TaskRecurrence::Biweekly, 2, 'Assist with a shopping list and store visit.');
        $this->upsertTask($theoPlan, 'Weekday breakfast setup', TaskRecurrence::Custom, 3, 'Set out breakfast items before 9 a.m.', TaskPreferredTiming::Morning, true, false, true, false, 'Every weekday morning');

        $harperPrior = $this->upsertCarePlan($clients['harper'], 'Harper Cole ISP 2025', $today->copy()->subYears(2)->toDateString(), $today->copy()->subYear()->endOfYear()->toDateString(), CarePlanStatus::Inactive, 'Superseded by the current plan.');
        $this->upsertTask($harperPrior, 'Daily living skills', TaskRecurrence::Daily, 1);
        $this->upsertTask($harperPrior, 'Community integration outing', TaskRecurrence::Weekly, 2);

        $harperPlan = $this->upsertCarePlan($clients['harper'], 'Harper Cole ISP 2026', $yearStart, $yearEnd, CarePlanStatus::Active);
        $this->deactivateDuplicateCarePlans($clients['harper'], $harperPlan);
        $this->upsertTask($harperPlan, 'Daily living skills', TaskRecurrence::Daily, 1);
        $this->upsertTask($harperPlan, 'Community integration outing', TaskRecurrence::Weekly, 2);
        $this->upsertTask($harperPlan, 'Home safety drill', TaskRecurrence::Monthly, 3, 'Practice fire and weather safety steps.');

        $noahPlan = $this->upsertCarePlan($clients['noah'], 'Noah Grant Independent Living Plan', $yearStart, $yearEnd, CarePlanStatus::Active);
        $this->upsertTask($noahPlan, 'Household support', TaskRecurrence::Weekly, 1, 'Laundry, dishes, and light housekeeping.');
        $this->upsertTask($noahPlan, 'Independent living skills', TaskRecurrence::Weekly, 2, 'Practice budgeting and appointment planning.');

        $malikPlan = $this->upsertCarePlan($clients['malik'], 'Malik Hassan Residential Plan', $today->copy()->subMonths(7)->toDateString(), $today->copy()->addMonths(5)->toDateString(), CarePlanStatus::Active);
        $this->upsertTask($malikPlan, 'Residential habilitation support', TaskRecurrence::Daily, 1);
        $this->upsertTask($malikPlan, 'Community recreation', TaskRecurrence::Weekly, 2);
        $this->upsertTask($malikPlan, 'Annual skills assessment', TaskRecurrence::Annual, 3);

        $rubyPlan = $this->upsertCarePlan($clients['ruby'], 'Ruby Foster Personal Care Plan', $yearStart, $today->copy()->addMonths(3)->toDateString(), CarePlanStatus::Active);
        $this->upsertTask($rubyPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->upsertTask($rubyPlan, 'Weekend family visit support', TaskRecurrence::Custom, 2, null, TaskPreferredTiming::Afternoon, true, false, true, false, 'Saturdays when family is available');

        $chloePlan = $this->upsertCarePlan($clients['chloe'], 'Chloe Nguyen Community Plan', $yearStart, $yearEnd, CarePlanStatus::Active);
        $this->upsertTask($chloePlan, 'Community outing', TaskRecurrence::Weekly, 1);
        $this->upsertTask($chloePlan, 'Appointment preparation', TaskRecurrence::Weekly, 2, 'Prepare clothing, documents, and transportation.');

        $ianPlan = $this->upsertCarePlan($clients['ian'], 'Ian Brooks Closed Care Plan', $today->copy()->subYears(2)->toDateString(), $today->copy()->subYear()->toDateString(), CarePlanStatus::Inactive, 'Historical plan retained with inactive client.');
        $this->upsertTask($ianPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->upsertTask($ianPlan, 'Weekly wellness walk', TaskRecurrence::Weekly, 2);

        return [
            'elena' => $elenaPlan,
            'theo' => $theoPlan,
            'harper' => $harperPlan,
            'noah' => $noahPlan,
            'malik' => $malikPlan,
            'ruby' => $rubyPlan,
            'chloe' => $chloePlan,
            'ian' => $ianPlan,
        ];
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedAvailability(array $people, CarbonInterface $today): void
    {
        $weekdayWindow = ['07:00:00', '15:00:00', PreferredDaypart::Morning];
        $this->upsertWeekly($people['maya'], [
            0 => null,
            1 => $weekdayWindow,
            2 => $weekdayWindow,
            3 => $weekdayWindow,
            4 => $weekdayWindow,
            5 => $weekdayWindow,
            6 => null,
        ]);

        $evening = ['15:00:00', '23:00:00', PreferredDaypart::Evening];
        $this->upsertWeekly($people['luis'], [
            0 => null,
            1 => $evening,
            2 => $evening,
            3 => $evening,
            4 => $evening,
            5 => $evening,
            6 => ['07:00:00', '15:00:00', PreferredDaypart::Morning],
        ]);

        $day = ['07:00:00', '19:00:00', PreferredDaypart::Morning];
        $this->upsertWeekly($people['nina'], [
            0 => $day,
            1 => $day,
            2 => $day,
            3 => null,
            4 => $day,
            5 => $day,
            6 => $day,
        ]);

        $limited = ['07:00:00', '15:00:00', PreferredDaypart::Morning];
        $this->upsertWeekly($people['owen'], [
            0 => null,
            1 => null,
            2 => $limited,
            3 => null,
            4 => $limited,
            5 => null,
            6 => null,
        ]);

        $office = ['09:00:00', '17:00:00', PreferredDaypart::Afternoon];
        $this->upsertWeekly($people['dana'], [
            0 => null,
            1 => $office,
            2 => $office,
            3 => $office,
            4 => $office,
            5 => $office,
            6 => null,
        ]);

        $weekend = ['10:00:00', '18:00:00', PreferredDaypart::Afternoon];
        $this->upsertWeekly($people['tess'], [
            0 => $weekend,
            1 => null,
            2 => null,
            3 => null,
            4 => null,
            5 => ['15:00:00', '21:00:00', PreferredDaypart::Evening],
            6 => $weekend,
        ]);

        $this->upsertWeekly($people['chris'], [
            0 => null,
            1 => null,
            2 => null,
            3 => null,
            4 => null,
            5 => null,
            6 => null,
        ]);

        $this->upsertException($people['dana'], $today->copy()->next(Carbon::SATURDAY)->toDateString(), true, '09:00:00', '13:00:00', 'dana-saturday-cover', 'One-off Saturday coverage approved.');
        $this->upsertException($people['maya'], $today->copy()->next(Carbon::SUNDAY)->toDateString(), false, null, null, 'maya-sunday-unavailable', 'Not available for Sunday fill-in.');

        $this->upsertAvailabilityRequest(
            $people['owen'],
            $people['owenUser'],
            $people['priyaUser'],
            AvailabilityRequestType::Weekly,
            $today->copy()->addWeek()->toDateString(),
            ReviewStatus::Pending,
            'owen-pending-friday',
            'Requesting Friday mornings for a recurring medical appointment change.',
            [
                'days' => [[
                    'weekday' => 5,
                    'is_available' => true,
                    'starts_at' => '07:00:00',
                    'ends_at' => '12:00:00',
                ]],
            ],
        );

        $this->upsertAvailabilityRequest(
            $people['luis'],
            $people['luisUser'],
            $people['jordanUser'],
            AvailabilityRequestType::Exception,
            $today->copy()->addDays(3)->toDateString(),
            ReviewStatus::Approved,
            'luis-approved-exception',
            'Can cover a one-off morning instead of evening.',
            [
                'exception_date' => $today->copy()->addDays(3)->toDateString(),
                'is_available' => true,
                'starts_at' => '07:00:00',
                'ends_at' => '12:00:00',
            ],
            $today->copy()->subDay(),
        );

        $this->upsertAvailabilityRequest(
            $people['tess'],
            $people['tessUser'],
            $people['priyaUser'],
            AvailabilityRequestType::Weekly,
            $today->copy()->subWeeks(2)->toDateString(),
            ReviewStatus::Rejected,
            'tess-rejected-weekdays',
            'Asked to add weekday mornings; caseload already covered.',
            [
                'days' => [[
                    'weekday' => 1,
                    'is_available' => true,
                    'starts_at' => '08:00:00',
                    'ends_at' => '12:00:00',
                ]],
            ],
            $today->copy()->subWeeks(2),
        );
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedTimeOff(array $people, CarbonInterface $today): void
    {
        $this->upsertTimeOff(
            $people['luis'],
            $people['luisUser'],
            $people['jordanUser'],
            $today->copy()->next(Carbon::FRIDAY)->toDateString(),
            $today->copy()->next(Carbon::FRIDAY)->toDateString(),
            ReviewStatus::Approved,
            'luis-upcoming-pto',
            'Family wedding — full day.',
            $today->copy()->subWeek(),
        );

        $this->upsertTimeOff(
            $people['nina'],
            $people['ninaUser'],
            null,
            $today->copy()->addDays(10)->toDateString(),
            $today->copy()->addDays(11)->toDateString(),
            ReviewStatus::Pending,
            'nina-pending-pto',
            'Requested two personal days.',
            $today->copy()->subDay(),
        );

        $this->upsertTimeOff(
            $people['maya'],
            $people['mayaUser'],
            $people['jordanUser'],
            $today->copy()->subWeeks(3)->toDateString(),
            $today->copy()->subWeeks(3)->toDateString(),
            ReviewStatus::Approved,
            'maya-historical-pto',
            'Used a personal day last month.',
            $today->copy()->subWeeks(4),
        );
    }

    /**
     * @param  array<string, mixed>  $people
     * @param  array<string, Client>  $clients
     * @param  array<string, CarePlan>  $plans
     */
    private function seedScheduleAndVisits(array $people, array $clients, array $plans, CarbonInterface $today): void
    {
        $settings = app(SettingsService::class);
        $day = ShiftTemplate::query()->where('code', 'day_7_3')->firstOrFail();
        $evening = ShiftTemplate::query()->where('code', 'evening_3_11')->firstOrFail();
        $overnight = ShiftTemplate::query()->where('code', 'overnight_11_7')->firstOrFail();
        $admin = $people['admin'];

        $series = ScheduledVisitSeries::query()->updateOrCreate(
            ['notes' => $this->marker('series-maya-elena-weekly')],
            [
                'client_id' => $clients['elena']->id,
                'employee_id' => $people['maya']->id,
                'supervisor_id' => $people['jordan']->id,
                'shift_template_id' => $day->id,
                'service_type' => 'Residential Habilitation',
                'starts_at' => null,
                'ends_at' => null,
                'pattern' => VisitRecurrencePattern::Weekly,
                'interval' => 1,
                'days_of_week' => [$today->dayOfWeek],
                'starts_on' => $today->copy()->subWeeks(2)->toDateString(),
                'ends_on' => $today->copy()->addWeeks(2)->toDateString(),
                'occurrence_count' => 5,
                'created_by_user_id' => $admin->id,
                'notes' => $this->marker('series-maya-elena-weekly'),
            ],
        );

        $pastTwo = $this->upsertScheduledVisit('maya-elena-week-past-2', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->copy()->subWeeks(2)->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Completed,
            'series_id' => $series->id,
            'created_by_user_id' => $admin->id,
            'notes' => 'Weekly residential morning support.',
        ]);
        $this->completeVisit($pastTwo, '07:04:00', '14:58:00', 'Morning support completed as planned.', 'No overnight concerns. Breakfast routine stayed on track.', 'complete');

        $yesterdayElena = $this->upsertScheduledVisit('maya-elena-yesterday', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->copy()->subDay()->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Visit-specific task set: omit community outing and add pharmacy pickup.',
        ]);
        $community = $plans['elena']->taskTemplates()->where('title', 'Community outing')->firstOrFail();
        ScheduledVisitTaskOverride::query()->updateOrCreate(
            [
                'scheduled_visit_id' => $yesterdayElena->id,
                'care_plan_task_template_id' => $community->id,
            ],
            [
                'included' => false,
                'exclusion_reason' => 'Community outing not planned for this visit.',
            ],
        );
        ScheduledVisitOneOffTask::query()->updateOrCreate(
            [
                'scheduled_visit_id' => $yesterdayElena->id,
                'title' => 'Pharmacy pickup support',
            ],
            [
                'instructions' => 'Escort to the pharmacy and return with the refill. Do not change the permanent care plan.',
                'note_required' => true,
                'is_required' => true,
                'sort_order' => 10,
            ],
        );
        $this->completeVisit($yesterdayElena, '07:08:00', '14:52:00', 'Morning ADLs, meals, and safety check completed. Pharmacy pickup added for this visit only.', 'Refill picked up. Community outing intentionally omitted for this visit.', 'complete-with-one-off');

        $todayMaya = $this->upsertScheduledVisit('maya-elena-today', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'series_id' => $series->id,
            'created_by_user_id' => $admin->id,
            'notes' => 'Primary DSP morning visit for clock-in demonstration.',
        ]);

        $this->upsertScheduledVisit('maya-elena-week-next', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->copy()->addWeek()->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'series_id' => $series->id,
            'created_by_user_id' => $admin->id,
            'notes' => 'Upcoming weekly residential morning support.',
        ]);

        $this->upsertScheduledVisit('maya-theo-tomorrow', [
            'client_id' => $clients['theo']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $evening->id,
            'service_date' => $today->copy()->addDay()->toDateString(),
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Upcoming evening personal care.',
        ]);

        $this->upsertScheduledVisit('maya-elena-cancelled', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['maya']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->copy()->subDays(5)->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Cancelled,
            'cancelled_at' => $settings->at($today->copy()->subDays(6)->toDateString(), '16:00:00'),
            'cancelled_by_user_id' => $people['jordanUser']->id,
            'cancellation_reason' => 'Cancelled after the client became unavailable.',
            'created_by_user_id' => $admin->id,
            'notes' => 'Cancelled after the client became unavailable.',
        ]);

        $this->upsertScheduledVisit('luis-harper-today', [
            'client_id' => $clients['harper']->id,
            'employee_id' => $people['luis']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $evening->id,
            'service_date' => $today->toDateString(),
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Evening community integration.',
        ]);

        $lateLuis = $this->upsertScheduledVisit('luis-harper-late', [
            'client_id' => $clients['harper']->id,
            'employee_id' => $people['luis']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->subDays(2)->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Partial-day outing with a late clock-in.',
        ]);
        $this->completeVisit($lateLuis, '09:18:00', '13:04:00', 'Community outing completed after a delayed arrival.', 'Client enjoyed the library visit.', 'late');

        $overnightVisit = $this->upsertScheduledVisit('luis-elena-overnight', [
            'client_id' => $clients['elena']->id,
            'employee_id' => $people['luis']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => $overnight->id,
            'service_date' => $today->copy()->subDays(4)->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Overnight coverage using the 11–7 template.',
        ]);
        $this->completeVisit($overnightVisit, '23:05:00', '07:02:00', 'Quiet overnight. Safety checks completed.', 'Morning DSP can continue the usual routine.', 'overnight', true);

        $this->upsertScheduledVisit('luis-harper-future', [
            'client_id' => $clients['harper']->id,
            'employee_id' => $people['luis']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->next(Carbon::SATURDAY)->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Upcoming Saturday outing inside Luis weekend availability.',
        ]);

        $corrected = $this->upsertScheduledVisit('dana-theo-corrected', [
            'client_id' => $clients['theo']->id,
            'employee_id' => $people['dana']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->subDays(3)->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'On-time visit with a later approved attendance correction.',
        ]);
        $danaVisit = $this->completeVisit($corrected, '09:03:00', '16:58:00', 'Personal care and breakfast setup completed.', 'Theo requested an earlier grocery stop next visit.', 'skip-one');
        $this->upsertCorrection($danaVisit, $people['jordanUser'], $people['admin'], AttendanceCorrectionStatus::Approved, '09:00:00', '17:00:00', 'dana-approved-correction', 'DSP forgot to clock in at the door. Original timestamps retained.');

        $this->upsertScheduledVisit('dana-theo-today', [
            'client_id' => $clients['theo']->id,
            'employee_id' => $people['dana']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => null,
            'service_date' => $today->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Weekday personal care window.',
        ]);

        $this->upsertScheduledVisit('dana-noah-tomorrow', [
            'client_id' => $clients['noah']->id,
            'employee_id' => $people['dana']->id,
            'supervisor_id' => $people['jordan']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->addDay()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Independent Living / Skill Development',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Household and independent living skills.',
        ]);

        $ninaCompleted = $this->upsertScheduledVisit('nina-malik-completed', [
            'client_id' => $clients['malik']->id,
            'employee_id' => $people['nina']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->copy()->subDays(2)->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Completed residential day support.',
        ]);
        $ninaVisit = $this->completeVisit($ninaCompleted, '07:02:00', '14:55:00', 'Residential support completed.', 'Malik slept well. Continue evening recreation plan.', 'complete');
        $this->upsertCorrection($ninaVisit, $people['priyaUser'], null, AttendanceCorrectionStatus::Pending, '07:00:00', '15:00:00', 'nina-pending-correction', 'Supervisor submitted a rounding correction.');

        $this->upsertScheduledVisit('nina-malik-today', [
            'client_id' => $clients['malik']->id,
            'employee_id' => $people['nina']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $day->id,
            'service_date' => $today->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'South caseload day visit.',
        ]);

        $this->upsertScheduledVisit('nina-ruby-evening', [
            'client_id' => $clients['ruby']->id,
            'employee_id' => $people['nina']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $evening->id,
            'service_date' => $today->copy()->addDays(3)->toDateString(),
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Upcoming evening personal care.',
        ]);

        $nextTuesday = $today->copy()->next(Carbon::TUESDAY);
        $nextThursday = $today->copy()->next(Carbon::THURSDAY);

        $this->upsertScheduledVisit('owen-malik-tuesday', [
            'client_id' => $clients['malik']->id,
            'employee_id' => $people['owen']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $day->id,
            'service_date' => $nextTuesday->toDateString(),
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Scheduled inside Owen’s Tuesday availability window.',
        ]);

        $lastTuesday = $today->copy()->previous(Carbon::TUESDAY);
        if ($lastTuesday->toDateString() === $today->toDateString()) {
            $lastTuesday = $today->copy()->subWeek();
        }

        $owenDone = $this->upsertScheduledVisit('owen-chloe-last-tuesday', [
            'client_id' => $clients['chloe']->id,
            'employee_id' => $people['owen']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $day->id,
            'service_date' => $lastTuesday->toDateString(),
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Completed Tuesday community support.',
        ]);
        $this->completeVisit($owenDone, '07:10:00', '14:50:00', 'Community outing completed.', 'Chloe asked to repeat the park walk.', 'complete');

        $this->upsertScheduledVisit('owen-chloe-thursday', [
            'client_id' => $clients['chloe']->id,
            'employee_id' => $people['owen']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => $day->id,
            'service_date' => $nextThursday->toDateString(),
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Scheduled inside Owen’s Thursday availability window.',
        ]);

        $tessEvening = (int) $today->dayOfWeek === Carbon::FRIDAY
            ? $today->toDateString()
            : $today->copy()->next(Carbon::FRIDAY)->toDateString();

        $this->upsertScheduledVisit('tess-ruby-today', [
            'client_id' => $clients['ruby']->id,
            'employee_id' => $people['tess']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => null,
            'service_date' => $tessEvening,
            'starts_at' => '15:00:00',
            'ends_at' => '21:00:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Friday evening window matching Tess availability.',
        ]);

        $this->upsertScheduledVisit('tess-chloe-weekend', [
            'client_id' => $clients['chloe']->id,
            'employee_id' => $people['tess']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->next(Carbon::SATURDAY)->toDateString(),
            'starts_at' => '10:00:00',
            'ends_at' => '16:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'created_by_user_id' => $admin->id,
            'notes' => 'Weekend community support matching Tess availability.',
        ]);

        $tessDone = $this->upsertScheduledVisit('tess-ruby-completed', [
            'client_id' => $clients['ruby']->id,
            'employee_id' => $people['tess']->id,
            'supervisor_id' => $people['priya']->id,
            'shift_template_id' => null,
            'service_date' => $today->copy()->previous(Carbon::SATURDAY)->toDateString(),
            'starts_at' => '10:00:00',
            'ends_at' => '16:00:00',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Completed,
            'created_by_user_id' => $admin->id,
            'notes' => 'Weekend personal care completed.',
        ]);
        $this->completeVisit($tessDone, '10:04:00', '15:56:00', 'Personal care and family visit support completed.', 'Family visit went well.', 'complete');

        unset($todayMaya);
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedAnnouncements(array $people, CarbonInterface $today): void
    {
        $this->upsertAnnouncement(
            $people['admin'],
            'Quarterly staff huddle',
            'Bring current availability and any scheduling constraints to Thursday’s huddle. No client details in the group notes.',
            AnnouncementAudience::Everyone,
            $today->copy()->subDays(2)->setTime(9, 0),
            $today->copy()->addWeeks(3),
            ['maya' => $people['mayaUser'], 'jordan' => $people['jordanUser']],
        );

        $this->upsertAnnouncement(
            $people['admin'],
            'Timesheet reminder',
            'Please review clock times before the weekly hours export. Corrections go through your supervisor.',
            AnnouncementAudience::Dsps,
            $today->copy()->subDays(5)->setTime(8, 30),
            $today->copy()->addWeeks(2),
            [],
        );

        $this->upsertAnnouncement(
            $people['jordanUser'],
            'North caseload coverage',
            'Luis has approved leave on the next Friday. Use Dana or Maya for daytime coverage only when already assigned.',
            AnnouncementAudience::Dsps,
            $today->copy()->subDay()->setTime(11, 0),
            $today->copy()->addWeeks(2),
            ['maya' => $people['mayaUser']],
        );
    }

    /**
     * @param  array<string, mixed>  $people
     */
    private function seedMessages(array $people, CarbonInterface $today): void
    {
        $settings = app(SettingsService::class);

        $coverage = $this->upsertConversation($people['admin'], $people['jordanUser']);
        $this->upsertMessage($coverage, $people['admin'], 'Can you confirm North caseload coverage for next Friday?', $settings->at($today->copy()->subDays(2)->toDateString(), '09:15:00'));
        $this->upsertMessage($coverage, $people['jordanUser'], 'Dana can cover Theo. Maya stays on Elena’s morning visit.', $settings->at($today->copy()->subDays(2)->toDateString(), '09:32:00'));
        $coverage->participants()->syncWithoutDetaching([
            $people['admin']->id => ['last_read_at' => $settings->at($today->copy()->subDays(2)->toDateString(), '10:00:00')],
            $people['jordanUser']->id => ['last_read_at' => $settings->at($today->copy()->subDays(2)->toDateString(), '10:00:00')],
        ]);

        $south = $this->upsertConversation($people['priyaUser'], $people['ninaUser']);
        $this->upsertMessage($south, $people['ninaUser'], 'Fire safety training is still in progress. I will finish it this week.', $settings->at($today->copy()->subDay()->toDateString(), '16:40:00'));
        $south->participants()->syncWithoutDetaching([
            $people['ninaUser']->id => ['last_read_at' => $settings->at($today->copy()->subDay()->toDateString(), '16:41:00')],
            $people['priyaUser']->id => ['last_read_at' => null],
        ]);
    }

    private function upsertUser(string $name, string $email, Role $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'password' => DemoSeeder::PASSWORD,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertEmployee(string $number, User $user, array $attributes): Employee
    {
        return Employee::query()->updateOrCreate(
            ['employee_number' => $number],
            [
                ...$attributes,
                'user_id' => $user->id,
            ],
        );
    }

    private function upsertDsp(
        User $user,
        string $number,
        string $first,
        ?string $middle,
        string $last,
        string $email,
        string $phone,
        string $dob,
        int $supervisorId,
        string $hiredOn,
        string $address,
        string $city,
        string $postal,
        string $emergencyName,
        string $emergencyRelationship,
        string $emergencyPhone,
    ): Employee {
        return $this->upsertEmployee($number, $user, [
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dob,
            'address_line_1' => $address,
            'city' => $city,
            'state' => 'OH',
            'postal_code' => $postal,
            'emergency_contact_name' => $emergencyName,
            'emergency_contact_relationship' => $emergencyRelationship,
            'emergency_contact_phone' => $emergencyPhone,
            'hired_on' => $hiredOn,
            'employment_status' => EmploymentStatus::Active,
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
            'supervisor_id' => $supervisorId,
        ]);
    }

    private function upsertClient(
        string $number,
        string $first,
        ?string $middle,
        string $last,
        int $supervisorId,
        ClientStatus $status,
        string $dob,
        string $address,
        string $phone,
        string $emergencyName,
    ): Client {
        return Client::query()->updateOrCreate(
            ['client_number' => $number],
            [
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'email' => strtolower($first).'.'.strtolower($last).'@clients.mdm.test',
                'phone' => $phone,
                'date_of_birth' => $dob,
                'address_line_1' => $address,
                'city' => 'Lakeside',
                'state' => 'OH',
                'postal_code' => '44010',
                'emergency_contact_name' => $emergencyName,
                'emergency_contact_relationship' => 'Guardian',
                'emergency_contact_phone' => '555-409-'.substr($phone, -4),
                'status' => $status,
                'supervisor_id' => $supervisorId,
                'notes' => 'Fictional demo client record.',
            ],
        );
    }

    private function upsertAssignment(
        Employee $dsp,
        Client $client,
        AssignmentStatus $status,
        string $startedOn,
        ?string $endedOn = null,
        ?string $notes = null,
    ): ClientDspAssignment {
        return ClientDspAssignment::query()->updateOrCreate(
            [
                'employee_id' => $dsp->id,
                'client_id' => $client->id,
            ],
            [
                'status' => $status,
                'started_on' => $startedOn,
                'ended_on' => $endedOn,
                'notes' => $notes,
            ],
        );
    }

    private function upsertCredential(
        Employee $employee,
        CredentialType $type,
        string $name,
        CredentialStatus $status,
        ?string $issuedOn,
        ?string $expiresOn,
        ?string $issuer = 'American Red Cross',
        ?string $number = null,
        ?string $notes = null,
    ): EmployeeCredential {
        return EmployeeCredential::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'type' => $type,
                'name' => $name,
            ],
            [
                'issuer' => $issuer,
                'credential_number' => $number,
                'issued_on' => $issuedOn,
                'expires_on' => $expiresOn,
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    private function upsertTraining(
        Employee $employee,
        string $title,
        string $provider,
        ?string $completedOn,
        ?string $expiresOn,
        string $hours,
        TrainingStatus $status,
        ?string $notes = null,
    ): EmployeeTraining {
        return EmployeeTraining::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'title' => $title,
            ],
            [
                'provider' => $provider,
                'completed_on' => $completedOn,
                'expires_on' => $expiresOn,
                'hours' => $hours,
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    private function upsertAuthorization(
        Client $client,
        string $number,
        string $payer,
        string $serviceType,
        string $startsOn,
        string $endsOn,
        string $units,
        AuthorizationUnit $unit,
        AuthorizationStatus $status,
        ?string $notes = null,
    ): ClientAuthorization {
        return ClientAuthorization::query()->updateOrCreate(
            ['authorization_number' => $number],
            [
                'client_id' => $client->id,
                'payer' => $payer,
                'service_type' => $serviceType,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'authorized_units' => $units,
                'unit' => $unit,
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    private function upsertCarePlan(
        Client $client,
        string $title,
        string $startsOn,
        ?string $endsOn,
        CarePlanStatus $status,
        ?string $notes = null,
    ): CarePlan {
        return CarePlan::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'title' => $title,
            ],
            [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    private function deactivateDuplicateCarePlans(Client $client, CarePlan $canonical): void
    {
        CarePlan::query()
            ->where('client_id', $client->id)
            ->whereKeyNot($canonical->id)
            ->where('status', CarePlanStatus::Active)
            ->whereIn('title', ['Elena Vasquez ISP', 'Harper Cole ISP'])
            ->update(['status' => CarePlanStatus::Inactive->value]);
    }

    private function upsertTask(
        CarePlan $carePlan,
        string $title,
        TaskRecurrence $recurrence,
        int $sortOrder,
        ?string $instructions = null,
        ?TaskPreferredTiming $timing = TaskPreferredTiming::DuringVisit,
        bool $required = true,
        bool $noteRequired = false,
        bool $canSkip = true,
        bool $critical = false,
        ?string $recurrenceDetail = null,
    ): CarePlanTaskTemplate {
        return CarePlanTaskTemplate::query()->updateOrCreate(
            [
                'care_plan_id' => $carePlan->id,
                'title' => $title,
            ],
            [
                'instructions' => $instructions,
                'recurrence' => $recurrence,
                'recurrence_detail' => $recurrenceDetail,
                'preferred_timing' => $timing,
                'is_required' => $required,
                'note_required' => $noteRequired,
                'can_skip' => $canSkip,
                'is_critical' => $critical,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: PreferredDaypart}|null>  $days
     */
    private function upsertWeekly(Employee $employee, array $days): void
    {
        foreach ($days as $weekday => $window) {
            DspWeeklyAvailability::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'weekday' => $weekday,
                ],
                [
                    'is_available' => $window !== null,
                    'starts_at' => $window[0] ?? null,
                    'ends_at' => $window[1] ?? null,
                    'preferred_daypart' => $window[2] ?? null,
                ],
            );
        }
    }

    private function upsertException(
        Employee $employee,
        string $date,
        bool $available,
        ?string $startsAt,
        ?string $endsAt,
        string $key,
        string $note,
    ): void {
        $marker = $this->marker($key);
        $existing = DspAvailabilityException::query()->where('note', 'like', '%'.$marker.'%')->first();

        $payload = [
            'employee_id' => $employee->id,
            'exception_date' => $date,
            'is_available' => $available,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => $marker.' '.$note,
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        DspAvailabilityException::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertAvailabilityRequest(
        Employee $employee,
        User $requester,
        ?User $reviewer,
        AvailabilityRequestType $type,
        string $effectiveOn,
        ReviewStatus $status,
        string $key,
        string $reason,
        array $payload,
        ?CarbonInterface $reviewedAt = null,
    ): void {
        $marker = $this->marker($key);
        $existing = DspAvailabilityRequest::query()->where('reason', 'like', '%'.$marker.'%')->first();

        $attributes = [
            'employee_id' => $employee->id,
            'requested_by_user_id' => $requester->id,
            'type' => $type,
            'effective_on' => $effectiveOn,
            'payload' => $payload,
            'reason' => $marker.' '.$reason,
            'status' => $status,
            'submitted_at' => ($reviewedAt ?? now())->copy()->subHours(6),
            'reviewed_by_user_id' => $status === ReviewStatus::Pending ? null : $reviewer?->id,
            'reviewed_at' => $status === ReviewStatus::Pending ? null : ($reviewedAt ?? now()),
            'review_note' => $status === ReviewStatus::Rejected ? 'Weekday mornings are already covered.' : ($status === ReviewStatus::Approved ? 'Approved for the stated window.' : null),
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        DspAvailabilityRequest::query()->create($attributes);
    }

    private function upsertTimeOff(
        Employee $employee,
        User $requester,
        ?User $reviewer,
        string $startsOn,
        string $endsOn,
        ReviewStatus $status,
        string $key,
        string $reason,
        CarbonInterface $submittedAt,
    ): void {
        $marker = $this->marker($key);
        $existing = EmployeeTimeOff::query()->where('reason', 'like', '%'.$marker.'%')->first();

        $attributes = [
            'employee_id' => $employee->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'reason' => $marker.' '.$reason,
            'status' => $status,
            'requested_by_user_id' => $requester->id,
            'reviewed_by_user_id' => $status === ReviewStatus::Pending ? null : $reviewer?->id,
            'reviewed_at' => $status === ReviewStatus::Pending ? null : $submittedAt->copy()->addDay(),
            'review_note' => $status === ReviewStatus::Approved ? 'Approved.' : null,
            'submitted_at' => $submittedAt,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        EmployeeTimeOff::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertScheduledVisit(string $key, array $attributes): ScheduledVisit
    {
        $marker = $this->marker($key);
        $notes = trim($marker.' '.($attributes['notes'] ?? ''));
        $existing = ScheduledVisit::query()->where('notes', 'like', '%'.$marker.'%')->first();
        $payload = [...$attributes, 'notes' => $notes];

        if ($existing !== null) {
            $existing->update($payload);

            return $existing->refresh();
        }

        return ScheduledVisit::query()->create($payload);
    }

    private function completeVisit(
        ScheduledVisit $scheduled,
        string $clockIn,
        string $clockOut,
        string $notes,
        string $handover,
        string $taskMode,
        bool $overnight = false,
    ): Visit {
        $settings = app(SettingsService::class);
        $date = $scheduled->service_date->toDateString();
        $clockedIn = $settings->at($date, $clockIn);
        $clockedOut = $settings->at($date, $clockOut);

        if ($overnight && $clockedOut->lte($clockedIn)) {
            $clockedOut = $clockedOut->addDay();
        }

        $visit = Visit::query()->updateOrCreate(
            ['scheduled_visit_id' => $scheduled->id],
            [
                'employee_id' => $scheduled->employee_id,
                'client_id' => $scheduled->client_id,
                'service_type' => $scheduled->service_type,
                'status' => VisitStatus::Completed,
                'clocked_in_at' => $clockedIn,
                'clocked_out_at' => $clockedOut,
                'clock_in_location_method' => ClockInLocationMethod::GpsUnavailable,
                'clock_in_location_status' => ClockInLocationStatus::Unavailable,
                'clock_in_unavailable_reason' => 'GPS unavailable in local demo.',
                'visit_notes' => $notes,
                'handover_note' => $handover,
                'clock_out_location_method' => ClockInLocationMethod::GpsUnavailable,
                'clock_out_location_status' => ClockInLocationStatus::Unavailable,
                'clock_out_unavailable_reason' => 'GPS unavailable in local demo.',
                'unfinished_required_acknowledged' => false,
            ],
        );

        $scheduled->update(['status' => ScheduledVisitStatus::Completed]);

        app(VisitTaskGenerator::class)->generate($visit->fresh() ?? $visit);

        $tasks = VisitTask::query()->where('visit_id', $visit->id)->orderBy('sort_order')->get();
        $skipReason = SkipReason::query()->where('code', SkipReason::CLIENT_REFUSED)->first();
        $skipped = false;

        foreach ($tasks as $index => $task) {
            if ($taskMode === 'skip-one' && ! $skipped && $task->can_skip && ! $task->is_critical && $skipReason !== null) {
                $task->update([
                    'status' => VisitTaskStatus::Skipped,
                    'skipped_at' => $clockedIn->addMinutes(40),
                    'skip_reason_id' => $skipReason->id,
                    'skip_comment' => 'Client declined this activity for today.',
                ]);
                $skipped = true;

                continue;
            }

            $task->update([
                'status' => VisitTaskStatus::Completed,
                'completed_at' => $clockedIn->addMinutes(20 + ($index * 12)),
                'completion_note' => $task->note_required ? 'Completed during the visit as planned.' : null,
            ]);
        }

        return $visit->refresh();
    }

    private function upsertCorrection(
        Visit $visit,
        User $requestedBy,
        ?User $reviewedBy,
        AttendanceCorrectionStatus $status,
        string $requestedIn,
        string $requestedOut,
        string $key,
        string $reason,
    ): void {
        $settings = app(SettingsService::class);
        $date = $visit->scheduledVisit->service_date->toDateString();
        $marker = $this->marker($key);
        $existing = AttendanceCorrection::query()->where('reason', 'like', '%'.$marker.'%')->first();

        $attributes = [
            'scheduled_visit_id' => $visit->scheduled_visit_id,
            'visit_id' => $visit->id,
            'status' => $status,
            'original_clocked_in_at' => $visit->clocked_in_at,
            'original_clocked_out_at' => $visit->clocked_out_at,
            'requested_clocked_in_at' => $settings->at($date, $requestedIn),
            'requested_clocked_out_at' => $settings->at($date, $requestedOut),
            'reason' => $marker.' '.$reason,
            'requested_by_user_id' => $requestedBy->id,
            'reviewed_by_user_id' => $status === AttendanceCorrectionStatus::Pending ? null : $reviewedBy?->id,
            'reviewed_at' => $status === AttendanceCorrectionStatus::Pending ? null : now()->subDay(),
            'review_note' => $status === AttendanceCorrectionStatus::Approved ? 'Approved. Original clock values remain on the visit.' : null,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        AttendanceCorrection::query()->create($attributes);
    }

    /**
     * @param  array<string, User>  $readers
     */
    private function upsertAnnouncement(
        User $author,
        string $title,
        string $body,
        AnnouncementAudience $audience,
        CarbonInterface $publishedAt,
        CarbonInterface $expiresAt,
        array $readers,
    ): void {
        $announcement = Announcement::query()->updateOrCreate(
            ['title' => $title],
            [
                'author_id' => $author->id,
                'body' => $body,
                'audience' => $audience,
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'is_active' => true,
            ],
        );

        $sync = [];

        foreach ($readers as $reader) {
            $sync[$reader->id] = ['read_at' => $publishedAt->copy()->addHours(3)];
        }

        if ($sync !== []) {
            $announcement->readers()->syncWithoutDetaching($sync);
        }
    }

    private function upsertConversation(User $first, User $second): Conversation
    {
        $conversation = Conversation::query()->updateOrCreate(
            ['participant_key' => Conversation::participantKeyFor($first, $second)],
            ['organization_id' => null],
        );

        $conversation->participants()->syncWithoutDetaching([$first->id, $second->id]);

        return $conversation;
    }

    private function upsertMessage(Conversation $conversation, User $sender, string $body, CarbonInterface $at): void
    {
        $message = ConversationMessage::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $message->created_at = $at;
        $message->updated_at = $at;
        $message->save();
    }

    private function marker(string $key): string
    {
        return self::MARKER_PREFIX.$key.']]';
    }
}

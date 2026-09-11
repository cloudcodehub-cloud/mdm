<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Enums\CarePlanStatus;
use App\Enums\ClientStatus;
use App\Enums\CredentialStatus;
use App\Enums\CredentialType;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\TrainingStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientAuthorization;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Models\ScheduledVisit;
use App\Models\ShiftTemplate;
use App\Models\SkipReason;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Shared password for all demo login accounts.
     */
    public const PASSWORD = 'password';

    /**
     * Seed fictional demo users, employees, clients, and DSP assignments.
     */
    public function run(): void
    {
        $this->call(TaskCatalogSeeder::class);
        $this->call(CareServiceSeeder::class);

        $admin = User::factory()->admin()->create([
            'name' => 'Avery Quinn',
            'email' => 'admin@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $jordanUser = User::factory()->supervisor()->create([
            'name' => 'Jordan Hale',
            'email' => 'jordan.hale@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $priyaUser = User::factory()->supervisor()->create([
            'name' => 'Priya Nair',
            'email' => 'priya.nair@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $jordan = Employee::query()->create([
            'employee_number' => 'EMP-1001',
            'user_id' => $jordanUser->id,
            'first_name' => 'Jordan',
            'middle_name' => 'Lee',
            'last_name' => 'Hale',
            'email' => 'jordan.hale@mdm.test',
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

        $priya = Employee::query()->create([
            'employee_number' => 'EMP-1002',
            'user_id' => $priyaUser->id,
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

        $mayaUser = User::factory()->dsp()->create([
            'name' => 'Maya Chen',
            'email' => 'maya.chen@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $luisUser = User::factory()->dsp()->create([
            'name' => 'Luis Ortega',
            'email' => 'luis.ortega@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $ninaUser = User::factory()->dsp()->create([
            'name' => 'Nina Brooks',
            'email' => 'nina.brooks@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $owenUser = User::factory()->dsp()->create([
            'name' => 'Owen Patel',
            'email' => 'owen.patel@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $saraUser = User::factory()->dsp()->create([
            'name' => 'Sara Kim',
            'email' => 'sara.kim@mdm.test',
            'password' => self::PASSWORD,
        ]);

        $maya = $this->createDsp(
            userId: $mayaUser->id,
            number: 'EMP-2001',
            first: 'Maya',
            middle: 'Grace',
            last: 'Chen',
            email: 'maya.chen@mdm.test',
            phone: '555-301-2001',
            dob: '1994-02-18',
            supervisorId: $jordan->id,
            hiredOn: '2021-01-11',
        );

        $luis = $this->createDsp(
            userId: $luisUser->id,
            number: 'EMP-2002',
            first: 'Luis',
            middle: null,
            last: 'Ortega',
            email: 'luis.ortega@mdm.test',
            phone: '555-301-2002',
            dob: '1992-09-27',
            supervisorId: $jordan->id,
            hiredOn: '2020-06-08',
        );

        $nina = $this->createDsp(
            userId: $ninaUser->id,
            number: 'EMP-2003',
            first: 'Nina',
            middle: 'Rae',
            last: 'Brooks',
            email: 'nina.brooks@mdm.test',
            phone: '555-301-2003',
            dob: '1996-05-04',
            supervisorId: $priya->id,
            hiredOn: '2022-04-19',
        );

        $owen = $this->createDsp(
            userId: $owenUser->id,
            number: 'EMP-2004',
            first: 'Owen',
            middle: null,
            last: 'Patel',
            email: 'owen.patel@mdm.test',
            phone: '555-301-2004',
            dob: '1991-12-22',
            supervisorId: $priya->id,
            hiredOn: '2019-10-02',
        );

        $sara = Employee::query()->create([
            'employee_number' => 'EMP-2005',
            'user_id' => $saraUser->id,
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

        $elena = $this->createClient('CLT-3001', 'Elena', 'Marie', 'Vasquez', $jordan->id, ClientStatus::Active);
        $theo = $this->createClient('CLT-3002', 'Theo', null, 'Anders', $jordan->id, ClientStatus::Active);
        $harper = $this->createClient('CLT-3003', 'Harper', 'Jane', 'Cole', $jordan->id, ClientStatus::Active);
        $malik = $this->createClient('CLT-3004', 'Malik', null, 'Hassan', $priya->id, ClientStatus::Active);
        $ruby = $this->createClient('CLT-3005', 'Ruby', 'Ann', 'Foster', $priya->id, ClientStatus::Active);
        $ian = $this->createClient('CLT-3006', 'Ian', null, 'Brooks', $priya->id, ClientStatus::Inactive);

        ClientDspAssignment::query()->create([
            'employee_id' => $maya->id,
            'client_id' => $elena->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2024-01-15',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $maya->id,
            'client_id' => $theo->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2024-03-01',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $luis->id,
            'client_id' => $elena->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2024-02-10',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $luis->id,
            'client_id' => $harper->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2023-11-20',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $nina->id,
            'client_id' => $malik->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2024-05-06',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $nina->id,
            'client_id' => $ruby->id,
            'status' => AssignmentStatus::Active,
            'started_on' => '2024-06-12',
        ]);

        ClientDspAssignment::query()->create([
            'employee_id' => $maya->id,
            'client_id' => $harper->id,
            'status' => AssignmentStatus::Inactive,
            'started_on' => '2023-01-08',
            'ended_on' => '2023-10-31',
            'notes' => 'Previous assignment closed when coverage changed.',
        ]);

        $this->seedShiftTemplates();
        $this->seedSkipReasons();
        $this->seedCredentials($jordan, $priya, $maya, $luis, $nina, $owen, $sara);
        $this->seedTrainings($maya, $luis, $nina, $owen, $sara);
        $this->seedAuthorizations($elena, $theo, $harper, $malik, $ruby, $ian);
        $this->seedClientServices($elena, $theo, $harper, $malik, $ruby);
        $this->seedCarePlans($elena, $theo, $harper, $malik, $ruby, $ian);
        $this->seedScheduledVisits($jordan, $priya, $maya, $luis, $nina, $elena, $theo, $harper, $malik, $ruby);

        if ($admin->employee()->exists()) {
            throw new \RuntimeException('Demo admin accounts must not have an employee profile.');
        }
    }

    private function seedShiftTemplates(): void
    {
        ShiftTemplate::query()->create([
            'name' => '7–3',
            'code' => 'day_7_3',
            'starts_at' => '07:00:00',
            'ends_at' => '15:00:00',
            'description' => 'Day shift, 7 a.m. to 3 p.m.',
            'is_active' => true,
        ]);

        ShiftTemplate::query()->create([
            'name' => '3–11',
            'code' => 'evening_3_11',
            'starts_at' => '15:00:00',
            'ends_at' => '23:00:00',
            'description' => 'Evening shift, 3 p.m. to 11 p.m.',
            'is_active' => true,
        ]);

        ShiftTemplate::query()->create([
            'name' => '11–7',
            'code' => 'overnight_11_7',
            'starts_at' => '23:00:00',
            'ends_at' => '07:00:00',
            'description' => 'Overnight shift, 11 p.m. to 7 a.m. next calendar day.',
            'is_active' => true,
        ]);
    }

    private function seedCredentials(
        Employee $jordan,
        Employee $priya,
        Employee $maya,
        Employee $luis,
        Employee $nina,
        Employee $owen,
        Employee $sara,
    ): void {
        $this->createCredential($jordan, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, '2024-03-01', '2026-03-01');
        $this->createCredential($priya, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, '2024-06-12', '2026-06-12');

        $this->createCredential($maya, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, '2025-01-10', '2027-01-10', 'American Red Cross', 'CPR-2001');
        $this->createCredential($maya, CredentialType::FirstAid, 'First Aid Certification', CredentialStatus::Active, '2025-01-10', '2027-01-10', 'American Red Cross', 'FA-2001');
        $this->createCredential($maya, CredentialType::DriversLicense, "Driver's License", CredentialStatus::Active, '2022-08-01', '2028-08-01', 'Ohio BMV', 'OH-DL-2001');

        $this->createCredential($luis, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Expired, '2022-02-01', '2024-02-01', 'American Red Cross', 'CPR-2002');
        $this->createCredential($luis, CredentialType::FirstAid, 'First Aid Certification', CredentialStatus::Active, '2025-04-18', '2027-04-18', 'American Red Cross', 'FA-2002');

        $this->createCredential($nina, CredentialType::BackgroundCheck, 'Background Check', CredentialStatus::Active, '2025-03-01', '2027-03-01', 'County Board', 'BGC-2003');
        $this->createCredential($nina, CredentialType::MedicationAdministration, 'Medication Administration', CredentialStatus::Active, '2024-11-15', '2026-11-15', 'Agency Nursing', 'MED-2003');

        $this->createCredential($owen, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Active, '2024-09-20', '2026-09-20');
        $this->createCredential($owen, CredentialType::TbScreening, 'TB Screening', CredentialStatus::Active, '2026-01-08', '2027-01-08', 'County Health');
        $this->createCredential($owen, CredentialType::DriversLicense, "Driver's License", CredentialStatus::Pending, null, null, 'Ohio BMV');

        $this->createCredential($sara, CredentialType::Cpr, 'CPR Certification', CredentialStatus::Expired, '2020-05-01', '2022-05-01', 'American Red Cross', 'CPR-2005', 'Historical credential retained after termination.');
    }

    private function seedTrainings(Employee $maya, Employee $luis, Employee $nina, Employee $owen, Employee $sara): void
    {
        EmployeeTraining::query()->create([
            'employee_id' => $maya->id,
            'title' => 'DSP Orientation',
            'provider' => 'Agency Training',
            'completed_on' => '2021-01-20',
            'expires_on' => null,
            'hours' => '8.00',
            'status' => TrainingStatus::Completed,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $maya->id,
            'title' => 'Bloodborne Pathogens',
            'provider' => 'Agency Training',
            'completed_on' => '2025-02-02',
            'expires_on' => '2027-02-02',
            'hours' => '2.00',
            'status' => TrainingStatus::Completed,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $luis->id,
            'title' => 'Crisis Intervention',
            'provider' => 'County Board',
            'completed_on' => '2025-06-01',
            'expires_on' => '2027-06-01',
            'hours' => '8.00',
            'status' => TrainingStatus::Completed,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $nina->id,
            'title' => 'Medication Administration',
            'provider' => 'Agency Nursing',
            'completed_on' => '2024-11-15',
            'expires_on' => '2026-11-15',
            'hours' => '4.00',
            'status' => TrainingStatus::Completed,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $nina->id,
            'title' => 'Fire Safety',
            'provider' => 'Agency Training',
            'completed_on' => null,
            'expires_on' => null,
            'hours' => '2.00',
            'status' => TrainingStatus::InProgress,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $owen->id,
            'title' => 'DSP Orientation',
            'provider' => 'Agency Training',
            'completed_on' => '2019-10-15',
            'expires_on' => null,
            'hours' => '8.00',
            'status' => TrainingStatus::Completed,
        ]);

        EmployeeTraining::query()->create([
            'employee_id' => $sara->id,
            'title' => 'DSP Orientation',
            'provider' => 'Agency Training',
            'completed_on' => '2017-05-10',
            'expires_on' => null,
            'hours' => '8.00',
            'status' => TrainingStatus::Completed,
            'notes' => 'Historical training retained after termination.',
        ]);
    }

    private function seedClientServices(
        Client $elena,
        Client $theo,
        Client $harper,
        Client $malik,
        Client $ruby,
    ): void {
        $bySlug = CareService::query()->pluck('id', 'slug');

        $elena->careServices()->sync(array_filter([
            $bySlug['residential-habilitation'] ?? null,
            $bySlug['community-integration'] ?? null,
        ]));
        $theo->careServices()->sync(array_filter([$bySlug['personal-care'] ?? null]));
        $harper->careServices()->sync(array_filter([
            $bySlug['community-integration'] ?? null,
            $bySlug['appointment-escort'] ?? null,
        ]));
        $malik->careServices()->sync(array_filter([
            $bySlug['residential-habilitation'] ?? null,
            $bySlug['overnight-supervision'] ?? null,
        ]));
        $ruby->careServices()->sync(array_filter([$bySlug['personal-care'] ?? null]));
    }

    private function seedAuthorizations(
        Client $elena,
        Client $theo,
        Client $harper,
        Client $malik,
        Client $ruby,
        Client $ian,
    ): void {
        ClientAuthorization::query()->create([
            'client_id' => $elena->id,
            'authorization_number' => 'AUTH-3001001',
            'payer' => 'Ohio Medicaid',
            'service_type' => 'Residential Habilitation',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'authorized_units' => '40.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Active,
            'notes' => 'Weekly authorized hours.',
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $theo->id,
            'authorization_number' => 'AUTH-3002001',
            'payer' => 'County Board Waiver',
            'service_type' => 'Personal Care',
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-12-31',
            'authorized_units' => '20.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Active,
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $harper->id,
            'authorization_number' => 'AUTH-3003001',
            'payer' => 'Ohio Medicaid',
            'service_type' => 'Community Integration',
            'starts_on' => '2026-10-01',
            'ends_on' => '2027-03-31',
            'authorized_units' => '15.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Pending,
            'notes' => 'Renewal pending payer approval.',
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $harper->id,
            'authorization_number' => 'AUTH-3003000',
            'payer' => 'Ohio Medicaid',
            'service_type' => 'Community Integration',
            'starts_on' => '2025-10-01',
            'ends_on' => '2026-09-30',
            'authorized_units' => '15.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Active,
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $malik->id,
            'authorization_number' => 'AUTH-3004001',
            'payer' => 'Ohio Medicaid',
            'service_type' => 'Residential Habilitation',
            'starts_on' => '2026-02-01',
            'ends_on' => '2027-01-31',
            'authorized_units' => '40.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Active,
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $ruby->id,
            'authorization_number' => 'AUTH-3005001',
            'payer' => 'Private Pay',
            'service_type' => 'Personal Care',
            'starts_on' => '2026-01-15',
            'ends_on' => '2026-12-15',
            'authorized_units' => '10.00',
            'unit' => AuthorizationUnit::Visit,
            'status' => AuthorizationStatus::Active,
        ]);

        ClientAuthorization::query()->create([
            'client_id' => $ian->id,
            'authorization_number' => 'AUTH-3006001',
            'payer' => 'County Board Waiver',
            'service_type' => 'Personal Care',
            'starts_on' => '2024-01-01',
            'ends_on' => '2025-06-30',
            'authorized_units' => '20.00',
            'unit' => AuthorizationUnit::Hour,
            'status' => AuthorizationStatus::Expired,
            'notes' => 'Closed with inactive client record.',
        ]);
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
            SkipReason::query()->create([
                ...$reason,
                'is_active' => true,
            ]);
        }
    }

    private function seedCarePlans(
        Client $elena,
        Client $theo,
        Client $harper,
        Client $malik,
        Client $ruby,
        Client $ian,
    ): void {
        $elenaPlan = $this->createCarePlan($elena, 'Elena Vasquez ISP 2026', '2026-01-01', '2026-12-31', CarePlanStatus::Active);
        $this->createTask($elenaPlan, 'Assist with morning ADLs', TaskRecurrence::Daily, 1, 'Support hygiene, dressing, and breakfast.');
        $this->createTask($elenaPlan, 'Community outing', TaskRecurrence::Weekly, 2, 'Support a community activity of Elena’s choosing.');
        $this->createTask($elenaPlan, 'Medication administration check', TaskRecurrence::Monthly, 3, 'Review medication count with the nurse.');
        $this->createTask($elenaPlan, 'Annual ISP goal review', TaskRecurrence::Annual, 4, 'Document progress toward independence goals.');

        $theoPlan = $this->createCarePlan($theo, 'Theo Anders Personal Care Plan', '2026-03-01', '2026-12-31', CarePlanStatus::Active);
        $this->createTask($theoPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->createTask($theoPlan, 'Grocery shopping support', TaskRecurrence::Biweekly, 2, 'Assist with a shopping list and store visit.');
        $this->createTask($theoPlan, 'Weekday breakfast setup', TaskRecurrence::Custom, 3, 'Set out breakfast items before 9 a.m.', 'Every weekday morning');

        $harperPrior = $this->createCarePlan($harper, 'Harper Cole ISP 2025', '2025-01-01', '2025-12-31', CarePlanStatus::Inactive, 'Superseded by the 2026 plan.');
        $this->createTask($harperPrior, 'Daily living skills', TaskRecurrence::Daily, 1);
        $this->createTask($harperPrior, 'Community integration outing', TaskRecurrence::Weekly, 2);

        $harperPlan = $this->createCarePlan($harper, 'Harper Cole ISP 2026', '2026-01-01', '2026-12-31', CarePlanStatus::Active);
        $this->createTask($harperPlan, 'Daily living skills', TaskRecurrence::Daily, 1);
        $this->createTask($harperPlan, 'Community integration outing', TaskRecurrence::Weekly, 2);
        $this->createTask($harperPlan, 'Home safety drill', TaskRecurrence::Monthly, 3, 'Practice fire and weather safety steps.');

        $malikPlan = $this->createCarePlan($malik, 'Malik Hassan Residential Plan', '2026-02-01', '2027-01-31', CarePlanStatus::Active);
        $this->createTask($malikPlan, 'Residential habilitation support', TaskRecurrence::Daily, 1);
        $this->createTask($malikPlan, 'Community recreation', TaskRecurrence::Weekly, 2);
        $this->createTask($malikPlan, 'Annual skills assessment', TaskRecurrence::Annual, 3);

        $rubyPlan = $this->createCarePlan($ruby, 'Ruby Foster Personal Care Plan', '2026-01-15', '2026-12-15', CarePlanStatus::Active);
        $this->createTask($rubyPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->createTask($rubyPlan, 'Weekend family visit support', TaskRecurrence::Custom, 2, null, 'Saturdays when family is available');

        $ianPlan = $this->createCarePlan($ian, 'Ian Brooks Closed Care Plan', '2024-01-01', '2025-06-30', CarePlanStatus::Inactive, 'Historical plan retained with inactive client.');
        $this->createTask($ianPlan, 'Personal care support', TaskRecurrence::Daily, 1);
        $this->createTask($ianPlan, 'Weekly wellness walk', TaskRecurrence::Weekly, 2);
    }

    private function seedScheduledVisits(
        Employee $jordan,
        Employee $priya,
        Employee $maya,
        Employee $luis,
        Employee $nina,
        Client $elena,
        Client $theo,
        Client $harper,
        Client $malik,
        Client $ruby,
    ): void {
        $day = ShiftTemplate::query()->where('code', 'day_7_3')->firstOrFail();
        $evening = ShiftTemplate::query()->where('code', 'evening_3_11')->firstOrFail();
        $overnight = ShiftTemplate::query()->where('code', 'overnight_11_7')->firstOrFail();

        ScheduledVisit::query()->create([
            'client_id' => $elena->id,
            'employee_id' => $maya->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => $day->id,
            'service_date' => '2026-09-11',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $theo->id,
            'employee_id' => $maya->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => $evening->id,
            'service_date' => '2026-09-12',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $elena->id,
            'employee_id' => $luis->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => $overnight->id,
            'service_date' => '2026-09-10',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'Overnight coverage using the 11–7 template.',
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $harper->id,
            'employee_id' => $luis->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => null,
            'service_date' => '2026-09-13',
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'Partial-day outing with explicit start and end times.',
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $malik->id,
            'employee_id' => $nina->id,
            'supervisor_id' => $priya->id,
            'shift_template_id' => $day->id,
            'service_date' => '2026-09-11',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $ruby->id,
            'employee_id' => $nina->id,
            'supervisor_id' => $priya->id,
            'shift_template_id' => $evening->id,
            'service_date' => '2026-09-14',
            'service_type' => 'Personal Care',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $elena->id,
            'employee_id' => $maya->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => $day->id,
            'service_date' => '2026-09-08',
            'service_type' => 'Residential Habilitation',
            'status' => ScheduledVisitStatus::Cancelled,
            'notes' => 'Cancelled after the client became unavailable.',
        ]);

        ScheduledVisit::query()->create([
            'client_id' => $harper->id,
            'employee_id' => $luis->id,
            'supervisor_id' => $jordan->id,
            'shift_template_id' => null,
            'service_date' => '2026-09-15',
            'starts_at' => '22:00:00',
            'ends_at' => '06:00:00',
            'service_type' => 'Community Integration',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'Explicit overnight window without a shift template.',
        ]);
    }

    private function createCarePlan(
        Client $client,
        string $title,
        string $startsOn,
        ?string $endsOn,
        CarePlanStatus $status,
        ?string $notes = null,
    ): CarePlan {
        return CarePlan::query()->create([
            'client_id' => $client->id,
            'title' => $title,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
            'notes' => $notes,
        ]);
    }

    private function createTask(
        CarePlan $carePlan,
        string $title,
        TaskRecurrence $recurrence,
        int $sortOrder,
        ?string $instructions = null,
        ?string $recurrenceDetail = null,
    ): CarePlanTaskTemplate {
        return CarePlanTaskTemplate::query()->create([
            'care_plan_id' => $carePlan->id,
            'title' => $title,
            'instructions' => $instructions,
            'recurrence' => $recurrence,
            'recurrence_detail' => $recurrenceDetail,
            'is_required' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createCredential(
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
        return EmployeeCredential::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'name' => $name,
            'issuer' => $issuer,
            'credential_number' => $number,
            'issued_on' => $issuedOn,
            'expires_on' => $expiresOn,
            'status' => $status,
            'notes' => $notes,
        ]);
    }

    private function createDsp(
        int $userId,
        string $number,
        string $first,
        ?string $middle,
        string $last,
        string $email,
        string $phone,
        string $dob,
        int $supervisorId,
        string $hiredOn,
    ): Employee {
        return Employee::query()->create([
            'employee_number' => $number,
            'user_id' => $userId,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dob,
            'address_line_1' => fake()->streetAddress(),
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44004',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => fake()->numerify('555-###-####'),
            'hired_on' => $hiredOn,
            'employment_status' => EmploymentStatus::Active,
            'job_title' => 'Direct Support Professional',
            'job_type' => JobType::Dsp,
            'supervisor_id' => $supervisorId,
        ]);
    }

    private function createClient(
        string $number,
        string $first,
        ?string $middle,
        string $last,
        int $supervisorId,
        ClientStatus $status,
    ): Client {
        return Client::query()->create([
            'client_number' => $number,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'email' => strtolower($first).'.'.strtolower($last).'@clients.mdm.test',
            'phone' => fake()->numerify('555-4##-####'),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-22 years')->format('Y-m-d'),
            'address_line_1' => fake()->streetAddress(),
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44010',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => 'Guardian',
            'emergency_contact_phone' => fake()->numerify('555-4##-####'),
            'status' => $status,
            'supervisor_id' => $supervisorId,
            'notes' => 'Fictional demo client record.',
        ]);
    }
}

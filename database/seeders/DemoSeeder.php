<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
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

        $this->createDsp(
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

        Employee::query()->create([
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
        $this->createClient('CLT-3006', 'Ian', null, 'Brooks', $priya->id, ClientStatus::Inactive);

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

        if ($admin->employee()->exists()) {
            throw new \RuntimeException('Demo admin accounts must not have an employee profile.');
        }
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

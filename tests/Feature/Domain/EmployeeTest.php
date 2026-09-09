<?php

namespace Tests\Feature\Domain;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_link_to_one_employee_profile(): void
    {
        $employee = Employee::factory()->dsp()->create();

        $this->assertNotNull($employee->user);
        $this->assertTrue($employee->user->isDsp());
        $this->assertTrue($employee->user->employee->is($employee));
        $this->assertSame($employee->email, $employee->user->email);
    }

    public function test_an_admin_user_does_not_require_an_employee_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertNull($admin->employee);
        $this->assertSame(Role::Admin, $admin->role);
    }

    public function test_employees_report_to_a_supervisor_employee(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();

        $this->assertTrue($dsp->supervisor->is($supervisor));
        $this->assertTrue($supervisor->reports->contains($dsp));
        $this->assertSame(JobType::Supervisor, $supervisor->job_type);
        $this->assertTrue($supervisor->user->isSupervisor());
    }

    public function test_employees_use_status_instead_of_hard_deletion(): void
    {
        $employee = Employee::factory()->terminated()->create();

        $this->assertSame(EmploymentStatus::Terminated, $employee->employment_status);
        $this->assertNotNull($employee->terminated_on);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employment_status' => EmploymentStatus::Terminated->value,
        ]);

        $employee->delete();

        $this->assertSoftDeleted($employee);
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));
    }

    public function test_employee_numbers_are_unique(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-9001']);

        $this->expectException(QueryException::class);

        Employee::factory()->create(['employee_number' => 'EMP-9001']);
    }
}

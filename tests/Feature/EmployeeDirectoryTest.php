<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\JobType;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_search_and_filter_employees(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $maya = Employee::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('employees.index', ['search' => 'Maya', 'job_type' => 'dsp', 'employment_status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employees/index')
                ->has('employees.data')
                ->where('employees.data.0.id', $maya->id)
                ->where('can.create', true)
            );
    }

    public function test_admin_can_create_a_dsp_with_a_login_account(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Alex',
                'last_name' => 'Rivera',
                'email' => 'alex.rivera@mdm.test',
                'job_type' => JobType::Dsp->value,
                'job_title' => 'Direct Support Professional',
                'employment_status' => EmploymentStatus::Active->value,
                'supervisor_id' => $supervisor->id,
                'create_login' => true,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect();

        $employee = Employee::query()->where('email', 'alex.rivera@mdm.test')->firstOrFail();

        $this->assertSame(JobType::Dsp, $employee->job_type);
        $this->assertTrue($employee->supervisor?->is($supervisor));
        $this->assertNotNull($employee->user);
        $this->assertTrue($employee->user->isDsp());
        $this->assertSame('alex.rivera@mdm.test', $employee->user->email);
        $this->assertNotNull($employee->employee_number);
    }

    public function test_admin_can_edit_employee_and_change_employment_status(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->dsp()->create();

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => 'Updated',
                'email' => $employee->email,
                'job_type' => JobType::Dsp->value,
                'employment_status' => EmploymentStatus::Inactive->value,
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->assertSame('Updated', $employee->fresh()?->last_name);
        $this->assertSame(EmploymentStatus::Inactive, $employee->fresh()?->employment_status);

        $this->actingAs($admin)
            ->patch(route('employees.status', $employee), [
                'employment_status' => EmploymentStatus::Terminated->value,
                'terminated_on' => '2026-09-10',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $employee->refresh();
        $this->assertSame(EmploymentStatus::Terminated, $employee->employment_status);
        $this->assertSame('2026-09-10', $employee->terminated_on?->toDateString());
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_employee_destroy_route_is_not_defined(): void
    {
        $this->assertFalse(Route::has('employees.destroy'));
    }

    public function test_supervisor_only_sees_assigned_reports(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $report = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $other = Employee::factory()->dsp()->create();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employees/index')
                ->where('can.create', false)
                ->has('employees.data', 2)
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.show', $report))
            ->assertOk();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.show', $other))
            ->assertForbidden();

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('employees.create'))
            ->assertForbidden();
    }

    public function test_dsp_cannot_open_employee_management(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.index'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.create'))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.show', $other))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('employees.show', $dsp))
            ->assertForbidden();

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('employees.store'), [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'job_type' => JobType::Dsp->value,
                'employment_status' => EmploymentStatus::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_admin_employee_detail_includes_existing_related_records(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();
        $maya = Employee::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('employees.show', $maya))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employees/show')
                ->where('employee.id', $maya->id)
                ->has('credentials')
                ->has('trainings')
                ->has('activity')
            );
    }
}

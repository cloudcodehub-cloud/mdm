<?php

namespace Tests\Feature\Auth;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_an_employee_profile_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertNull($admin->employee);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_active_supervisor_can_log_in(): void
    {
        $user = $this->userFor(Employee::factory()->supervisor()->create());

        $this->assertSame(EmploymentStatus::Active, $user->employee?->employment_status);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_active_dsp_can_log_in(): void
    {
        $user = $this->userFor(Employee::factory()->dsp()->create());

        $this->assertSame(EmploymentStatus::Active, $user->employee?->employment_status);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_terminated_dsp_cannot_log_in_and_records_are_preserved(): void
    {
        $employee = Employee::factory()->dsp()->terminated()->create();
        $user = $this->userFor($employee);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Dsp->value]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => $user->id,
            'employment_status' => EmploymentStatus::Terminated->value,
        ]);
    }

    public function test_inactive_dsp_cannot_log_in_and_records_are_preserved(): void
    {
        $employee = Employee::factory()->dsp()->inactive()->create();
        $user = $this->userFor($employee);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Dsp->value]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => $user->id,
            'employment_status' => EmploymentStatus::Inactive->value,
        ]);
    }

    private function userFor(Employee $employee): User
    {
        $employee->refresh();

        $user = $employee->user;

        $this->assertNotNull($user);

        return $user;
    }
}

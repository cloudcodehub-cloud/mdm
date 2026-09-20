<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6AAuthAndRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_authenticated_routes_to_login(): void
    {
        foreach ([
            'dashboard',
            'employees.index',
            'clients.index',
            'scheduled-visits.index',
            'operations.index',
            'attendance.index',
            'compliance.index',
            'reports.index',
            'messages.index',
            'profile.edit',
        ] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_remember_me_login_does_not_error_and_reaches_the_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_terminated_employee_session_is_logged_out_on_the_next_request(): void
    {
        $employee = Employee::factory()->dsp()->create();
        $user = $employee->user()->firstOrFail();

        $employee->update(['employment_status' => EmploymentStatus::Terminated]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => Role::Dsp->value]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employment_status' => EmploymentStatus::Terminated->value,
        ]);
    }

    public function test_each_role_lands_on_its_own_dashboard_payload(): void
    {
        $this->seed(DemoSeeder::class);

        $this->actingAs(User::query()->where('email', 'admin@mdm.test')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Admin->value));

        $this->actingAs(User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Supervisor->value));

        $this->actingAs(User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Dsp->value));
    }

    public function test_dsp_cannot_open_admin_or_supervisor_modules_by_url(): void
    {
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        foreach ([
            'employees.index',
            'employees.create',
            'clients.index',
            'clients.create',
            'supervisors.index',
            'operations.index',
            'visit-exceptions.index',
            'compliance.index',
            'reports.index',
            'availability-requests.index',
            'care-services.index',
            'settings.general.edit',
            'scheduled-visits.create',
        ] as $route) {
            $this->actingAs($dsp)->get(route($route))->assertForbidden();
        }
    }

    public function test_supervisor_cannot_open_admin_only_modules_by_url(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        $this->actingAs($supervisor)->get(route('supervisors.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('care-services.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('settings.general.edit'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('my-availability.index'))->assertForbidden();
    }

    public function test_supervisor_cannot_submit_dsp_availability_or_time_off_for_themselves(): void
    {
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        $this->actingAs($supervisor)
            ->post(route('my-availability.time-off'), [
                'starts_on' => '2026-09-20',
                'ends_on' => '2026-09-21',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_open_role_gated_modules(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            'employees.index',
            'clients.index',
            'supervisors.index',
            'operations.index',
            'attendance.index',
            'compliance.index',
            'reports.index',
            'availability-requests.index',
            'care-services.index',
            'settings.general.edit',
            'messages.index',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_dsp_schedule_board_does_not_receive_the_supervisor_directory(): void
    {
        $dsp = Employee::factory()->dsp()->create()->user()->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('scheduled-visits.calendar'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('scheduled-visits/calendar')
                ->has('supervisors', 0)
                ->where('can.create', false)
                ->where('can.filter_dsps', false));
    }
}

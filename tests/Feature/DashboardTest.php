<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_admin_dashboard_uses_seeded_operational_counts(): void
    {
        $this->seed(DemoSeeder::class);
        Carbon::setTestNow('2026-09-11');

        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Admin->value)
                ->where('dashboard.metrics.0.key', 'active_employees')
                ->where('dashboard.metrics.0.value', 6)
                ->where('dashboard.metrics.1.key', 'active_clients')
                ->where('dashboard.metrics.1.value', 5)
                ->where('dashboard.metrics.2.key', 'visits_today')
                ->where('dashboard.metrics.2.value', 2)
                ->has('dashboard.today_visits', 2)
                ->has('dashboard.upcoming_visits')
                ->has('dashboard.attention_items')
                ->has('dashboard.activity')
            );
    }

    public function test_supervisor_dashboard_is_scoped_to_assigned_caseload(): void
    {
        $this->seed(DemoSeeder::class);
        Carbon::setTestNow('2026-09-11');

        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Supervisor->value)
                ->where('dashboard.metrics.0.key', 'assigned_dsps')
                ->where('dashboard.metrics.0.value', 2)
                ->where('dashboard.metrics.1.key', 'assigned_clients')
                ->where('dashboard.metrics.1.value', 3)
                ->where('dashboard.metrics.2.key', 'visits_today')
                ->where('dashboard.metrics.2.value', 1)
                ->has('dashboard.assigned_dsps', 2)
                ->has('dashboard.assigned_clients', 3)
                ->has('dashboard.today_visits', 1)
            );
    }

    public function test_dsp_dashboard_shows_own_schedule(): void
    {
        $this->seed(DemoSeeder::class);
        Carbon::setTestNow('2026-09-11');

        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($dsp)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Dsp->value)
                ->where('dashboard.metrics.0.key', 'visits_today')
                ->where('dashboard.metrics.0.value', 1)
                ->has('dashboard.today_visits', 1)
                ->where('dashboard.today_visits.0.client.name', 'Elena Marie Vasquez')
                ->where('dashboard.today_visits.0.service_type', 'Residential Habilitation')
                ->has('dashboard.upcoming_visits')
                ->has('dashboard.assigned_clients', 2)
            );
    }

    public function test_dsp_cannot_open_admin_modules(): void
    {
        $this->seed(DemoSeeder::class);
        $dsp = User::query()->where('email', 'maya.chen@mdm.test')->firstOrFail();

        $this->actingAs($dsp)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($dsp)->get(route('clients.index'))->assertForbidden();
        $this->actingAs($dsp)->get(route('supervisors.index'))->assertForbidden();
    }

    public function test_admin_can_open_placeholder_modules(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/coming-soon')
                ->where('title', 'Attendance')
            );
    }

    public function test_admin_can_open_employee_and_client_directories(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employees/index'));

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('clients/index'));
    }
}

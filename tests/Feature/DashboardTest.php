<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\ScheduledVisitStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

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
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_admin_dashboard_uses_seeded_operational_counts(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin@mdm.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Admin->value)
                ->where('dashboard.metrics.0.key', 'active_employees')
                ->where('dashboard.metrics.0.value', 8)
                ->where('dashboard.metrics.1.key', 'active_clients')
                ->where('dashboard.metrics.1.value', 7)
                ->where('dashboard.metrics.2.key', 'visits_today')
                ->where('dashboard.metrics.2.value', 5)
                ->has('dashboard.today_visits', 5)
                ->where('dashboard.active_visit', null)
                ->where('dashboard.clock_in_visit', null)
                ->has('dashboard.upcoming_visits')
                ->has('dashboard.attention_items')
                ->has('dashboard.activity')
                ->has('dashboard.today_visit_summary')
                ->has('dashboard.visit_trend', 7)
                ->has('dashboard.compliance_health')
            );
    }

    public function test_supervisor_dashboard_is_scoped_to_assigned_caseload(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->seed(DemoSeeder::class);

        $supervisor = User::query()->where('email', 'jordan.hale@mdm.test')->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('dashboard.role', Role::Supervisor->value)
                ->where('dashboard.metrics.0.key', 'assigned_dsps')
                ->where('dashboard.metrics.0.value', 3)
                ->where('dashboard.metrics.1.key', 'assigned_clients')
                ->where('dashboard.metrics.1.value', 4)
                ->where('dashboard.metrics.2.key', 'visits_today')
                ->where('dashboard.metrics.2.value', 3)
                ->has('dashboard.assigned_dsps', 3)
                ->has('dashboard.assigned_clients', 4)
                ->has('dashboard.today_visits', 3)
                ->has('dashboard.today_visit_summary')
                ->has('dashboard.open_exceptions')
            );
    }

    public function test_dsp_dashboard_shows_own_schedule(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');
        $this->seed(DemoSeeder::class);

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
                ->where('dashboard.active_visit', null)
                ->where('dashboard.clock_in_visit.client.name', 'Elena Marie Vasquez')
                ->has('dashboard.assigned_clients', 2)
                ->where('dashboard.compliance_health', null)
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
                ->component('attendance/index')
                ->has('records.data')
            );

        $this->actingAs($admin)
            ->get(route('compliance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('compliance/index'));

        $this->actingAs($admin)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('scheduled-visits/index'));
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

    public function test_dsp_dashboard_includes_active_visit_task_progress(): void
    {
        Carbon::setTestNow('2026-09-11 14:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'status' => ScheduledVisitStatus::InProgress,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::InProgress,
        ]);

        VisitTask::factory()->for($visit)->count(3)->create([
            'status' => VisitTaskStatus::Completed,
        ]);
        VisitTask::factory()->for($visit)->create([
            'status' => VisitTaskStatus::Pending,
        ]);
        VisitTask::factory()->for($visit)->create([
            'status' => VisitTaskStatus::Skipped,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.active_visit.id', $visit->id)
                ->where('dashboard.active_visit.task_progress.completed', 3)
                ->where('dashboard.active_visit.task_progress.pending', 1)
                ->where('dashboard.active_visit.task_progress.skipped', 1)
                ->where('dashboard.active_visit.task_progress.total', 5)
                ->where('dashboard.active_visit.task_progress.percent', 60)
                ->where('activeWork.id', $visit->id)
                ->where('activeWork.task_progress.percent', 60)
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeWork.id', $visit->id)
            );
    }
}

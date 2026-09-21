<?php

namespace Tests\Feature;

use App\Enums\CarePlanStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitExceptionType;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitException;
use App\Models\VisitTask;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6D1InteractionTest extends TestCase
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

    public function test_login_form_still_authenticates_through_the_store_route(): void
    {
        $user = User::factory()->admin()->create();

        $this->get(route('login'))->assertOk();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_active_dsp_payload_includes_next_actionable_task_and_clears_after_clock_out(): void
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

        VisitTask::factory()->for($visit)->create([
            'title' => 'Completed hygiene',
            'status' => VisitTaskStatus::Completed,
            'sort_order' => 1,
            'is_required' => true,
            'is_critical' => false,
        ]);
        VisitTask::factory()->for($visit)->create([
            'title' => 'Optional outing',
            'status' => VisitTaskStatus::Pending,
            'sort_order' => 2,
            'is_required' => false,
            'is_critical' => false,
        ]);
        VisitTask::factory()->for($visit)->create([
            'title' => 'Prepare evening meal',
            'status' => VisitTaskStatus::Pending,
            'sort_order' => 3,
            'is_required' => true,
            'is_critical' => true,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.active_visit.id', $visit->id)
                ->where('dashboard.active_visit.task_progress.completed', 1)
                ->where('dashboard.active_visit.task_progress.pending', 2)
                ->where('dashboard.active_visit.next_task.title', 'Prepare evening meal')
                ->where('activeWork.id', $visit->id)
                ->where('activeWork.next_task.title', 'Prepare evening meal')
            );

        $visit->update([
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.active_visit', null)
                ->where('activeWork', null)
            );
    }

    public function test_recently_completed_visits_are_scoped_and_hidden_from_dsp(): void
    {
        Carbon::setTestNow('2026-09-11 16:00:00');

        $supervisor = Employee::factory()->supervisor()->create();
        $otherSupervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $otherDsp = Employee::factory()->dsp()->forSupervisor($otherSupervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $otherClient = Client::factory()->forSupervisor($otherSupervisor)->create();

        $inScope = $this->completedVisit($client, $dsp, now()->subMinutes(18), 'Residential Habilitation');
        VisitException::factory()->for($inScope)->create([
            'type' => VisitExceptionType::ClientRefusal,
        ]);
        $this->completedVisit($otherClient, $otherDsp, now()->subMinutes(5), 'Personal Care');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard.recently_completed_visits', 2)
                ->where('dashboard.recently_completed_visits.0.id', $inScope->id)
                ->where('dashboard.recently_completed_visits.0.has_high_priority_open', true)
                ->where('dashboard.recently_completed_visits.0.href', route('visit-exceptions.show', VisitException::query()->where('visit_id', $inScope->id)->firstOrFail()))
            );

        $this->actingAs($supervisor->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard.recently_completed_visits', 1)
                ->where('dashboard.recently_completed_visits.0.id', $inScope->id)
                ->where('dashboard.recently_completed_visits.0.client.name', $client->full_name)
            );

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.recently_completed_visits', [])
                ->where('dashboard.profile_attention.employees', [])
                ->where('dashboard.profile_attention.clients', [])
            );
    }

    public function test_profile_attention_tabs_are_scoped_and_sorted_worst_first(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $critical = Employee::factory()->dsp()->forSupervisor($supervisor)->create([
            'date_of_birth' => null,
            'hired_on' => null,
            'address_line_1' => null,
            'first_name' => 'Critical',
            'last_name' => 'Worker',
        ]);
        $attention = Employee::factory()->dsp()->forSupervisor($supervisor)->create([
            'date_of_birth' => '1990-01-01',
            'hired_on' => null,
            'address_line_1' => '1 Main St',
            'first_name' => 'Attention',
            'last_name' => 'Worker',
        ]);
        Employee::factory()->dsp()->create([
            'first_name' => 'OutOfScope',
            'last_name' => 'Worker',
            'date_of_birth' => null,
            'hired_on' => null,
            'address_line_1' => null,
        ]);

        $payload = app(DashboardService::class)->forUser($supervisor->user()->firstOrFail());
        $employees = collect($payload['profile_attention']['employees']);

        $this->assertSame('employee-'.$critical->id, $employees->first()['id']);
        $this->assertFalse($employees->contains(fn (array $row): bool => str_contains($row['name'], 'OutOfScope')));
        $this->assertTrue($employees->contains(fn (array $row): bool => $row['id'] === 'employee-'.$attention->id));
        $this->assertLessThanOrEqual(
            $employees->first(fn (array $row): bool => $row['id'] === 'employee-'.$attention->id)['percent'],
            $employees->first()['percent'],
        );
    }

    public function test_upcoming_visit_detail_includes_assigned_tasks_and_hides_them_from_unauthorized_dsp(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');

        $dsp = Employee::factory()->dsp()->create();
        $other = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'status' => CarePlanStatus::Active,
            'starts_on' => '2026-09-01',
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Morning hygiene support',
            'recurrence' => TaskRecurrence::Daily,
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-22',
            'status' => ScheduledVisitStatus::Scheduled,
            'notes' => 'Bring evening medication list.',
        ]);
        ScheduledVisitOneOffTask::query()->create([
            'scheduled_visit_id' => $scheduled->id,
            'title' => 'Pharmacy pickup support',
            'instructions' => null,
            'note_required' => false,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.notes', 'Bring evening medication list.')
                ->has('visit.assigned_visit_tasks')
                ->where('visit.assigned_visit_tasks.0.title', 'Morning hygiene support')
                ->where('visit.assigned_visit_tasks.0.is_required', true)
                ->where('visit.assigned_visit_tasks.1.title', 'Pharmacy pickup support')
                ->where('visit.assigned_visit_tasks.1.source', 'one_off')
                ->missing('visit.authorizations')
                ->missing('visit.billing')
            );

        $this->actingAs($other->user()->firstOrFail())
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertForbidden();
    }

    public function test_completed_visit_page_is_available_as_a_review_destination(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $visit = $this->completedVisit($client, $dsp, now(), 'Residential Habilitation');

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/show')
                ->where('visit.status', VisitStatus::Completed->value)
                ->where('visit.id', $visit->id)
            );
    }

    private function completedVisit(Client $client, Employee $dsp, CarbonInterface $completedAt, string $serviceType): Visit
    {
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => $completedAt->toDateString(),
            'status' => ScheduledVisitStatus::Completed,
            'service_type' => $serviceType,
        ]);

        return Visit::factory()->forScheduledVisit($scheduled)->create([
            'status' => VisitStatus::Completed,
            'service_type' => $serviceType,
            'clocked_in_at' => $completedAt->copy()->subHours(2),
            'clocked_out_at' => $completedAt,
        ]);
    }
}

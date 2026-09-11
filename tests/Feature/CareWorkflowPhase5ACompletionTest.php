<?php

namespace Tests\Feature;

use App\Enums\CarePlanStatus;
use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitStatus;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitOneOffTask;
use App\Models\TaskCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\VisitTaskGenerator;
use Database\Seeders\CareServiceSeeder;
use Database\Seeders\TaskCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CareWorkflowPhase5ACompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TaskCatalogSeeder::class);
        $this->seed(CareServiceSeeder::class);
        Carbon::setTestNow('2026-09-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_manage_service_catalog_and_supervisor_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create()->user()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('care-services.store'), [
                'name' => 'Custom Program',
                'description' => 'Agency-specific program.',
                'is_active' => true,
            ])
            ->assertRedirect(route('care-services.index'));

        $this->assertDatabaseHas('care_services', ['name' => 'Custom Program']);

        $this->actingAs($supervisor)
            ->get(route('care-services.index'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('care-services.store'), ['name' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_staged_client_create_saves_services_and_optional_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $personal = CareService::query()->where('slug', 'personal-care')->firstOrFail();
        $meal = TaskCatalogItem::query()->where('slug', 'meal-preparation')->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('clients.store'), [
                'first_name' => 'Jordan',
                'last_name' => 'Setup',
                'status' => 'active',
            ]);

        $client = Client::query()->where('last_name', 'Setup')->firstOrFail();
        $response->assertRedirect(route('clients.setup.edit', $client));

        $this->actingAs($admin)
            ->post(route('clients.setup.update', $client), [
                'service_ids' => [$personal->id],
                'tasks' => [
                    ['catalog_item_id' => $meal->id, 'recurrence' => TaskRecurrence::Daily->value],
                ],
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertTrue($client->careServices()->whereKey($personal->id)->exists());
        $this->assertSame(1, $client->carePlans()->firstOrFail()->taskTemplates()->active()->count());
    }

    public function test_supervisor_can_assign_services_to_scoped_client_only(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $foreign = Client::factory()->forSupervisor($other)->create();
        $user = $supervisor->user()->firstOrFail();
        $service = CareService::query()->where('slug', 'respite-care')->firstOrFail();

        $this->actingAs($user)
            ->post(route('clients.setup.update', $client), [
                'service_ids' => [$service->id],
                'tasks' => [],
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->actingAs($user)
            ->post(route('clients.setup.update', $foreign), [
                'service_ids' => [$service->id],
                'tasks' => [],
            ])
            ->assertForbidden();
    }

    public function test_visit_preview_marks_due_and_not_due_without_creating_visit_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-01',
            'status' => CarePlanStatus::Active,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Meal Preparation',
            'recurrence' => TaskRecurrence::Daily,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Grocery Shopping',
            'recurrence' => TaskRecurrence::Weekly,
            'weekdays' => [5],
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('scheduled-visits.care-preview', [
                'client_id' => $client->id,
                'service_date' => '2026-09-12',
            ]));

        $response->assertOk()
            ->assertJsonPath('tasks.0.title', 'Meal Preparation')
            ->assertJsonPath('tasks.0.due', true)
            ->assertJsonPath('tasks.1.title', 'Grocery Shopping')
            ->assertJsonPath('tasks.1.due', false);

        $this->assertSame(0, VisitTask::query()->count());
    }

    public function test_one_off_visit_task_is_generated_at_clock_in_without_changing_care_plan(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-01',
            'status' => CarePlanStatus::Active,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Meal Preparation',
            'recurrence' => TaskRecurrence::Daily,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-12',
            'status' => ScheduledVisitStatus::Scheduled,
        ]);
        ScheduledVisitOneOffTask::query()->create([
            'scheduled_visit_id' => $scheduled->id,
            'title' => 'Pick up prescription before returning home.',
            'is_required' => true,
            'note_required' => false,
            'sort_order' => 1,
        ]);

        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
            'status' => VisitStatus::InProgress,
        ]);

        app(VisitTaskGenerator::class)->generate($visit);

        $this->assertTrue(VisitTask::query()->where('title', 'Meal Preparation')->exists());
        $this->assertTrue(VisitTask::query()->where('title', 'Pick up prescription before returning home.')->exists());
        $this->assertSame(1, $plan->taskTemplates()->active()->count());
        $this->assertNull(
            VisitTask::query()
                ->where('title', 'Pick up prescription before returning home.')
                ->first()?->care_plan_task_template_id,
        );
    }

    public function test_completed_scheduled_visit_exposes_completed_phase_not_upcoming(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-11',
            'status' => ScheduledVisitStatus::Completed,
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
            'status' => VisitStatus::Completed,
            'clocked_out_at' => now()->subDay(),
        ]);
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'title' => 'Meal Preparation',
            'status' => VisitTaskStatus::Completed,
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('scheduled-visits.show', $scheduled))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('visit.visit_phase', 'completed')
                ->where('can.clock_in', false)
                ->where('visit.recorded_visit.id', $visit->id));
    }

    public function test_scheduling_client_options_include_assigned_services(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $service = CareService::query()->where('slug', 'personal-care')->firstOrFail();
        $client->careServices()->sync([$service->id]);

        $this->actingAs($admin)
            ->get(route('scheduled-visits.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.0.services.0.name', 'Personal Care'));
    }
}

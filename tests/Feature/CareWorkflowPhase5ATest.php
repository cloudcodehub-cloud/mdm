<?php

namespace Tests\Feature;

use App\Enums\CarePlanStatus;
use App\Enums\TaskRecurrence;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\ConversationMessage;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\TaskCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitTask;
use App\Services\SettingsService;
use App\Services\TaskRecurrenceMatcher;
use App\Services\VisitTaskGenerator;
use Database\Seeders\TaskCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CareWorkflowPhase5ATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TaskCatalogSeeder::class);
        Carbon::setTestNow('2026-09-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_catalog_and_bundles_are_seeded(): void
    {
        $this->assertTrue(TaskCatalogItem::query()->where('slug', 'meal-preparation')->exists());
        $this->assertSame(TaskRecurrence::Biweekly, TaskCatalogItem::query()->where('slug', 'grocery-shopping')->firstOrFail()->default_recurrence);
        $this->assertDatabaseHas('task_catalog_bundles', ['slug' => 'morning-adl-routine']);
    }

    public function test_admin_can_save_catalog_tasks_with_client_overrides(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        $meal = TaskCatalogItem::query()->where('slug', 'meal-preparation')->firstOrFail();
        $grocery = TaskCatalogItem::query()->where('slug', 'grocery-shopping')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('care-plans.tasks.sync', $plan), [
                'tasks' => [
                    [
                        'catalog_item_id' => $meal->id,
                        'recurrence' => TaskRecurrence::Weekly->value,
                        'weekdays' => [1, 3, 5],
                        'instructions' => 'Offer half portions first.',
                    ],
                    [
                        'catalog_item_id' => $grocery->id,
                    ],
                    [
                        'catalog_item_id' => $meal->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $plan->taskTemplates()->active()->count());
        $saved = $plan->taskTemplates()->active()->where('catalog_item_id', $meal->id)->firstOrFail();
        $this->assertSame(TaskRecurrence::Weekly, $saved->recurrence);
        $this->assertSame([1, 3, 5], $saved->weekdays);
        $this->assertSame('Offer half portions first.', $saved->instructions);
    }

    public function test_supervisor_can_manage_scoped_care_plans_only(): void
    {
        $supervisor = Employee::factory()->supervisor()->create();
        $other = Employee::factory()->supervisor()->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        $foreign = Client::factory()->forSupervisor($other)->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        $foreignPlan = CarePlan::factory()->forClient($foreign)->create();
        $user = $supervisor->user()->firstOrFail();
        $item = TaskCatalogItem::query()->where('slug', 'grooming')->firstOrFail();

        $this->actingAs($user)
            ->put(route('care-plans.tasks.sync', $plan), [
                'tasks' => [['catalog_item_id' => $item->id]],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('care-plans.tasks.sync', $foreignPlan), [
                'tasks' => [['catalog_item_id' => $item->id]],
            ])
            ->assertForbidden();
    }

    public function test_dsp_cannot_alter_care_plan_tasks(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        $item = TaskCatalogItem::query()->firstOrFail();

        $this->actingAs($dsp->user()->firstOrFail())
            ->put(route('care-plans.tasks.sync', $plan), [
                'tasks' => [['catalog_item_id' => $item->id]],
            ])
            ->assertForbidden();
    }

    public function test_quarterly_and_weekday_recurrence_apply_on_expected_days(): void
    {
        $plan = CarePlan::factory()->create(['starts_on' => '2026-01-15']);
        $quarterly = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Quarterly review',
            'recurrence' => TaskRecurrence::Quarterly,
        ]);
        $weekdays = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Weekday meal',
            'recurrence' => TaskRecurrence::Weekly,
            'weekdays' => [1, 3, 5],
        ]);

        $matcher = app(TaskRecurrenceMatcher::class);

        $this->assertTrue($matcher->appliesOn($quarterly, Carbon::parse('2026-04-15')));
        $this->assertFalse($matcher->appliesOn($quarterly, Carbon::parse('2026-02-15')));
        $this->assertTrue($matcher->appliesOn($weekdays, Carbon::parse('2026-09-14')));
        $this->assertFalse($matcher->appliesOn($weekdays, Carbon::parse('2026-09-15')));
    }

    public function test_future_recurring_tasks_are_visible_but_not_generated_until_clock_in(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $plan = CarePlan::factory()->forClient($client)->create([
            'starts_on' => '2026-09-01',
            'status' => CarePlanStatus::Active,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Grocery Shopping',
            'recurrence' => TaskRecurrence::Weekly,
            'weekdays' => [5],
        ]);
        ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => '2026-09-18',
        ]);

        $this->actingAs($dsp->user()->firstOrFail())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('clients/show')
                ->has('care_overview.upcoming', 1)
                ->where('care_overview.upcoming.0.title', 'Grocery Shopping')
                ->where('care_overview.upcoming.0.completable', false));

        $this->assertSame(0, VisitTask::query()->count());
    }

    public function test_care_note_history_and_contextual_message_reuse_main_messaging(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $previous = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();
        $plan = CarePlan::factory()->forClient($client)->create();
        $template = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Meal Preparation',
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($previous)->create([
            'service_date' => '2026-09-11',
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $previous->id,
            'client_id' => $client->id,
        ]);
        VisitTask::factory()->create([
            'visit_id' => $visit->id,
            'care_plan_task_template_id' => $template->id,
            'title' => 'Meal Preparation',
            'status' => VisitTaskStatus::Completed,
            'completion_note' => 'Client had little appetite and finished half the meal.',
            'completed_at' => now()->subDay(),
        ]);

        $actor = $dsp->user()->firstOrFail();
        $recipient = $previous->user()->firstOrFail();

        $this->actingAs($actor)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('care_overview.history.0.task_title', 'Meal Preparation')
                ->where('care_overview.history.0.previous_dsp_available', true));

        $this->actingAs($actor)
            ->post(route('conversations.store'), [
                'user_id' => $recipient->id,
                'body' => 'Checking on last night’s meal notes.',
                'stay' => true,
                'care_context_label' => 'Elena Vasquez · Meal Preparation · Sep 11 Visit',
                'care_context_client_id' => $client->id,
                'care_context_visit_id' => $visit->id,
                'care_context_task_title' => 'Meal Preparation',
            ])
            ->assertRedirect();

        $message = ConversationMessage::query()->firstOrFail();
        $this->assertSame('Elena Vasquez · Meal Preparation · Sep 11 Visit', $message->care_context['label'] ?? null);
    }

    public function test_dsp_attendance_and_schedule_hide_global_employee_filters(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $user = $dsp->user()->firstOrFail();

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.filter_employees', false));

        $this->actingAs($user)
            ->get(route('scheduled-visits.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.filter_dsps', false));
    }

    public function test_visit_generator_snapshots_note_and_skip_flags(): void
    {
        $dsp = Employee::factory()->dsp()->create();
        $client = Client::factory()->create();
        $plan = CarePlan::factory()->forClient($client)->create(['starts_on' => app(SettingsService::class)->today()]);
        $template = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Progress Note',
            'note_required' => true,
            'can_skip' => false,
            'is_critical' => true,
        ]);
        $scheduled = ScheduledVisit::factory()->forClient($client)->forDsp($dsp)->create([
            'service_date' => app(SettingsService::class)->today(),
        ]);
        $visit = Visit::factory()->forScheduledVisit($scheduled)->create([
            'employee_id' => $dsp->id,
            'client_id' => $client->id,
        ]);

        app(VisitTaskGenerator::class)->generate($visit);

        $task = VisitTask::query()->where('care_plan_task_template_id', $template->id)->firstOrFail();
        $this->assertTrue($task->note_required);
        $this->assertFalse($task->can_skip);
        $this->assertTrue($task->is_critical);
    }
}

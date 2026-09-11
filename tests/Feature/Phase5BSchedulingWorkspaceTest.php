<?php

namespace Tests\Feature;

use App\Enums\ScheduledVisitStatus;
use App\Enums\TaskRecurrence;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\CareService;
use App\Models\Client;
use App\Models\ClientDspAssignment;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\User;
use App\Models\VisitTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase5BSchedulingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_visit_persists_multiple_client_services(): void
    {
        [$admin, $dsp, $client] = $this->actors();
        $personal = CareService::factory()->create(['name' => 'Personal Care', 'sort_order' => 1]);
        $community = CareService::factory()->create(['name' => 'Community Integration', 'sort_order' => 2]);
        $client->careServices()->attach([$personal->id, $community->id]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00',
                'ends_at' => '13:00',
                'service_ids' => [$personal->id, $community->id],
                'status' => ScheduledVisitStatus::Scheduled->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $visit = ScheduledVisit::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$personal->id, $community->id],
            $visit->careServices()->pluck('care_services.id')->all(),
        );
        $this->assertSame('Personal Care · Community Integration', $visit->service_type);
    }

    public function test_required_due_task_exclusion_requires_reason_and_skips_clock_in_generation(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        [$admin, $dsp, $client] = $this->actors();
        $plan = CarePlan::factory()->forClient($client)->create(['starts_on' => '2026-09-01']);
        $required = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Medication Support',
            'recurrence' => TaskRecurrence::Daily,
            'is_required' => true,
            'is_critical' => true,
        ]);
        $optional = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Walk outdoors',
            'recurrence' => TaskRecurrence::Weekly,
            'weekdays' => [3],
            'is_required' => false,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00',
                'ends_at' => '13:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'task_overrides' => [[
                    'care_plan_task_template_id' => $required->id,
                    'included' => '0',
                    'exclusion_reason' => '',
                ]],
            ])
            ->assertSessionHasErrors('task_overrides.0.exclusion_reason');

        $this->actingAs($admin)
            ->post(route('scheduled-visits.store'), [
                'client_id' => $client->id,
                'employee_id' => $dsp->id,
                'service_date' => '2026-09-22',
                'starts_at' => '09:00',
                'ends_at' => '13:00',
                'service_type' => 'Personal Care',
                'status' => ScheduledVisitStatus::Scheduled->value,
                'one_off_tasks' => [[
                    'title' => 'Pick up prescription',
                    'instructions' => 'Before returning home',
                ]],
                'task_overrides' => [
                    [
                        'care_plan_task_template_id' => $required->id,
                        'included' => '0',
                        'exclusion_reason' => 'Family completing this today',
                    ],
                    [
                        'care_plan_task_template_id' => $optional->id,
                        'included' => '1',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $scheduled = ScheduledVisit::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertCount(2, $scheduled->taskOverrides);
        $this->assertCount(1, $scheduled->oneOffTasks);

        $this->actingAs($dsp->user()->firstOrFail())
            ->post(route('scheduled-visits.clock-in', $scheduled), [
                'location_method' => 'gps_unavailable',
                'location_status' => 'denied',
                'unavailable_reason' => 'Test clock-in',
            ])
            ->assertRedirect();

        $titles = VisitTask::query()->pluck('title')->all();
        $this->assertNotContains('Medication Support', $titles);
        $this->assertContains('Walk outdoors', $titles);
        $this->assertContains('Pick up prescription', $titles);
        $this->assertSame(1, CarePlanTaskTemplate::query()->whereKey($required->id)->count());
    }

    public function test_unconfigured_availability_is_flagged_not_confirmed(): void
    {
        [$admin, $dsp, $client] = $this->actors();

        $this->actingAs($admin)
            ->get(route('scheduled-visits.availability-board', [
                'client_id' => $client->id,
                'service_date' => '2026-09-16',
                'starts_at' => '15:00',
                'ends_at' => '23:00',
                'service_type' => 'Personal Care',
            ]))
            ->assertOk()
            ->assertJsonPath('dsps.0.availability_confirmed', false)
            ->assertJsonFragment(['state' => 'unconfirmed']);
    }

    /**
     * @return array{0: User, 1: Employee, 2: Client}
     */
    private function actors(): array
    {
        $admin = User::factory()->admin()->create();
        $supervisor = Employee::factory()->supervisor()->create();
        $dsp = Employee::factory()->dsp()->forSupervisor($supervisor)->create();
        $client = Client::factory()->forSupervisor($supervisor)->create();
        ClientDspAssignment::factory()->forDsp($dsp)->forClient($client)->create();

        return [$admin, $dsp, $client];
    }
}

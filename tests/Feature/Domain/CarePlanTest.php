<?php

namespace Tests\Feature\Domain;

use App\Enums\CarePlanStatus;
use App\Enums\TaskRecurrence;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_have_active_and_historical_care_plans(): void
    {
        $client = Client::factory()->create();

        $active = CarePlan::factory()->forClient($client)->create([
            'title' => 'Current ISP',
        ]);

        $historical = CarePlan::factory()->inactive()->forClient($client)->create();

        $this->assertCount(2, $client->carePlans);
        $this->assertTrue($active->isCurrentlyActive());
        $this->assertFalse($historical->isCurrentlyActive());
        $this->assertSame(CarePlanStatus::Inactive, $historical->status);
        $this->assertCount(1, CarePlan::query()->currentlyActive()->get());
    }

    public function test_task_templates_belong_to_a_care_plan_with_supported_recurrence(): void
    {
        $plan = CarePlan::factory()->create();

        $daily = CarePlanTaskTemplate::factory()->forCarePlan($plan)->create([
            'title' => 'Morning ADLs',
            'recurrence' => TaskRecurrence::Daily,
            'sort_order' => 1,
        ]);

        $custom = CarePlanTaskTemplate::factory()
            ->forCarePlan($plan)
            ->recurrence(TaskRecurrence::Custom, 'Weekdays before 9 a.m.')
            ->create([
                'title' => 'Weekday breakfast setup',
                'sort_order' => 2,
            ]);

        CarePlanTaskTemplate::factory()->forCarePlan($plan)->recurrence(TaskRecurrence::Weekly)->create([
            'title' => 'Community outing',
            'sort_order' => 3,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->recurrence(TaskRecurrence::Biweekly)->create([
            'title' => 'Grocery support',
            'sort_order' => 4,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->recurrence(TaskRecurrence::Monthly)->create([
            'title' => 'Safety drill',
            'sort_order' => 5,
        ]);
        CarePlanTaskTemplate::factory()->forCarePlan($plan)->recurrence(TaskRecurrence::Annual)->create([
            'title' => 'ISP review',
            'sort_order' => 6,
        ]);

        $this->assertCount(6, $plan->taskTemplates);
        $this->assertSame(TaskRecurrence::Daily, $daily->recurrence);
        $this->assertSame(TaskRecurrence::Custom, $custom->recurrence);
        $this->assertSame('Weekdays before 9 a.m.', $custom->recurrence_detail);
        $this->assertSame('Morning ADLs', $plan->taskTemplates->first()?->title);
    }
}

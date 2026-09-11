<?php

namespace Database\Factories;

use App\Enums\TaskRecurrence;
use App\Enums\VisitTaskStatus;
use App\Models\CarePlanTaskTemplate;
use App\Models\Visit;
use App\Models\VisitTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitTask>
 */
class VisitTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'care_plan_task_template_id' => CarePlanTaskTemplate::factory(),
            'title' => 'Assist with morning ADLs',
            'instructions' => null,
            'recurrence' => TaskRecurrence::Daily,
            'recurrence_detail' => null,
            'is_required' => true,
            'note_required' => false,
            'can_skip' => true,
            'is_critical' => false,
            'sort_order' => 1,
            'status' => VisitTaskStatus::Pending,
        ];
    }
}

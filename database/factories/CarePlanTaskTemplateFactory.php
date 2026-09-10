<?php

namespace Database\Factories;

use App\Enums\TaskRecurrence;
use App\Models\CarePlan;
use App\Models\CarePlanTaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarePlanTaskTemplate>
 */
class CarePlanTaskTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'care_plan_id' => CarePlan::factory(),
            'title' => 'Assist with morning ADLs',
            'instructions' => 'Support hygiene, dressing, and breakfast as accepted by the client.',
            'recurrence' => TaskRecurrence::Daily,
            'recurrence_detail' => null,
            'is_required' => true,
            'sort_order' => 1,
        ];
    }

    public function recurrence(TaskRecurrence $recurrence, ?string $detail = null): static
    {
        return $this->state(fn (array $attributes) => [
            'recurrence' => $recurrence,
            'recurrence_detail' => $detail,
        ]);
    }

    public function forCarePlan(CarePlan $carePlan): static
    {
        return $this->state(fn (array $attributes) => [
            'care_plan_id' => $carePlan->id,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\VisitAssignmentKind;
use App\Models\Employee;
use App\Models\ScheduledVisit;
use App\Models\ScheduledVisitAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledVisitAssignment>
 */
class ScheduledVisitAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_visit_id' => ScheduledVisit::factory(),
            'employee_id' => Employee::factory()->dsp(),
            'assigned_by_user_id' => null,
            'kind' => VisitAssignmentKind::Assigned,
            'reason' => null,
            'assigned_at' => now(),
            'ended_at' => null,
        ];
    }
}

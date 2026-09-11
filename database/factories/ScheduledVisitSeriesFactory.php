<?php

namespace Database\Factories;

use App\Enums\VisitRecurrencePattern;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ScheduledVisitSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledVisitSeries>
 */
class ScheduledVisitSeriesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'employee_id' => Employee::factory()->dsp(),
            'supervisor_id' => null,
            'shift_template_id' => null,
            'service_type' => 'Personal Care',
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'notes' => null,
            'pattern' => VisitRecurrencePattern::Weekly,
            'interval' => 1,
            'days_of_week' => null,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(4)->toDateString(),
            'occurrence_count' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\DspWeeklyAvailability;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DspWeeklyAvailability>
 */
class DspWeeklyAvailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'weekday' => 1,
            'is_available' => true,
            'starts_at' => '07:00:00',
            'ends_at' => '23:00:00',
            'preferred_daypart' => null,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }
}

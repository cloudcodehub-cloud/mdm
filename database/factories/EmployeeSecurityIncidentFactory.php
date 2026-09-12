<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSecurityIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSecurityIncident>
 */
class EmployeeSecurityIncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'incident' => fake()->sentence(4),
            'city_state' => fake()->city().', OH',
            'charge' => fake()->optional()->words(3, true),
            'sort_order' => 0,
        ];
    }
}

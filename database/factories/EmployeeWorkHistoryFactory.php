<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeWorkHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeWorkHistory>
 */
class EmployeeWorkHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'started_on' => fake()->dateTimeBetween('-6 years', '-2 years')->format('Y-m-d'),
            'ended_on' => fake()->boolean(70)
                ? fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d')
                : null,
            'job_title' => fake()->jobTitle(),
            'employer' => fake()->company(),
            'employer_phone' => fake()->numerify('555-###-####'),
            'employer_address' => fake()->streetAddress().', '.fake()->city().', OH',
            'reason_for_leaving' => fake()->optional()->sentence(),
            'job_duties' => fake()->optional()->paragraph(),
            'sort_order' => 0,
        ];
    }
}

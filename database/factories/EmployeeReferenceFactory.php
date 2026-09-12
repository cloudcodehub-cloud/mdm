<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeReference>
 */
class EmployeeReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'name' => fake()->name(),
            'address' => fake()->streetAddress().', '.fake()->city().', OH',
            'home_phone' => fake()->numerify('555-###-####'),
            'work_phone' => fake()->optional()->numerify('555-###-####'),
            'relationship' => fake()->randomElement(['Colleague', 'Former supervisor', 'Friend']),
            'sort_order' => 0,
        ];
    }
}

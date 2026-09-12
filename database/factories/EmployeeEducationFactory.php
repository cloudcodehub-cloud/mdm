<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeEducation>
 */
class EmployeeEducationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'level' => EducationLevel::HighSchool,
            'institution_name' => fake()->company().' High School',
            'city' => fake()->city(),
            'state' => 'OH',
            'country' => 'USA',
            'graduated' => true,
            'years_completed' => 4,
            'degree' => null,
            'sort_order' => 0,
        ];
    }
}

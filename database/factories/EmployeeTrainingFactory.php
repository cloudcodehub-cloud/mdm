<?php

namespace Database\Factories;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTraining>
 */
class EmployeeTrainingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->dsp(),
            'title' => fake()->randomElement([
                'DSP Orientation',
                'Bloodborne Pathogens',
                'Medication Administration',
                'Crisis Intervention',
                'Fire Safety',
            ]),
            'provider' => fake()->randomElement(['Agency Training', 'County Board', 'Red Cross']),
            'completed_on' => fake()->dateTimeBetween('-18 months', '-2 weeks')->format('Y-m-d'),
            'expires_on' => fake()->dateTimeBetween('+1 month', '+18 months')->format('Y-m-d'),
            'hours' => fake()->randomElement(['2.00', '4.00', '8.00']),
            'status' => TrainingStatus::Completed,
            'notes' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_on' => null,
            'expires_on' => null,
            'status' => TrainingStatus::InProgress,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_on' => now()->subYears(2)->toDateString(),
            'expires_on' => now()->subMonth()->toDateString(),
            'status' => TrainingStatus::Expired,
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
